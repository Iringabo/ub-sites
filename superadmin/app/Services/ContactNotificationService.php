<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\I18n\Time;
use Throwable;

final class ContactNotificationService
{
    private const ENV_ENABLED   = 'CONTACT_NOTIFICATION_ENABLED';
    private const ENV_RECIPIENT = 'CONTACT_NOTIFICATION_RECIPIENT';
    private const ENV_FROM      = 'CONTACT_NOTIFICATION_FROM_EMAIL';
    private const ENV_FROM_NAME = 'CONTACT_NOTIFICATION_FROM_NAME';
    private const ENV_PROTOCOL  = 'CONTACT_NOTIFICATION_PROTOCOL';
    private const ENV_HOST      = 'CONTACT_NOTIFICATION_SMTP_HOST';
    private const ENV_PORT      = 'CONTACT_NOTIFICATION_SMTP_PORT';
    private const ENV_CRYPTO    = 'CONTACT_NOTIFICATION_SMTP_CRYPTO';
    private const ENV_USER      = 'CONTACT_NOTIFICATION_SMTP_USER';
    private const ENV_PASS      = 'CONTACT_NOTIFICATION_SMTP_PASS';

    public function notify(array $message): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $recipient = $this->envString(self::ENV_RECIPIENT);
        $fromEmail  = $this->envString(self::ENV_FROM);
        $fromName   = $this->envString(self::ENV_FROM_NAME);
        $protocol   = $this->envString(self::ENV_PROTOCOL);
        $smtpHost   = $this->envString(self::ENV_HOST);
        $smtpPort   = $this->envPort(self::ENV_PORT);
        $smtpCrypto = $this->envString(self::ENV_CRYPTO);
        $smtpUser   = $this->envString(self::ENV_USER);
        $smtpPass   = $this->envString(self::ENV_PASS);

        if ($recipient === null || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            log_message('error', 'La notification du message de contact a été ignorée: destinataire invalide.');

            return false;
        }

        if ($fromEmail === null || ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            log_message('error', 'La notification du message de contact a été ignorée: adresse expéditeur invalide.');

            return false;
        }

        if ($fromName === null || $protocol === null || $smtpHost === null || $smtpPort === null || $smtpCrypto === null || $smtpUser === null || $smtpPass === null) {
            log_message('error', 'La notification du message de contact a été ignorée: configuration incomplète.');

            return false;
        }

        try {
            $email = service('email');
            $email->initialize([
                'fromEmail'   => $fromEmail,
                'fromName'    => $fromName,
                'protocol'    => $protocol,
                'SMTPHost'    => $smtpHost,
                'SMTPPort'    => $smtpPort,
                'SMTPCrypto'  => $smtpCrypto,
                'SMTPUser'    => $smtpUser,
                'SMTPPass'    => $smtpPass,
                'mailType'    => 'text',
                'charset'     => 'UTF-8',
                'validate'    => true,
                'newline'     => "\r\n",
                'CRLF'        => "\r\n",
            ]);

            $email->setFrom($fromEmail, $fromName);
            $email->setTo($recipient);

            $replyTo = $this->envStringFromValue($message['email'] ?? null);
            if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $replyName = $this->cleanSingleLine((string) ($message['name'] ?? ''));
                $email->setReplyTo($replyTo, $replyName);
            }

            $subject = $this->cleanSingleLine((string) ($message['subject'] ?? ''));
            $email->setSubject('Nouveau message de contact : ' . $subject);
            $email->setMessage($this->buildBody($message));

            if ($email->send()) {
                return true;
            }
        } catch (Throwable) {
            // On ne remonte jamais l'échec technique au visiteur.
        }

        log_message('error', 'L’envoi de la notification du message de contact a échoué.');

        return false;
    }

    private function isEnabled(): bool
    {
        return (bool) env(self::ENV_ENABLED, false);
    }

    private function envString(string $key): ?string
    {
        $value = env($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function envStringFromValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function envPort(string $key): ?int
    {
        $value = env($key);

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $port = (int) $value;

        return $port > 0 && $port <= 65535 ? $port : null;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function buildBody(array $message): string
    {
        $name    = $this->cleanSingleLine((string) ($message['name'] ?? ''));
        $email   = $this->cleanSingleLine((string) ($message['email'] ?? ''));
        $phone   = $this->cleanSingleLine((string) ($message['phone'] ?? ''));
        $subject = $this->cleanSingleLine((string) ($message['subject'] ?? ''));
        $body    = $this->cleanMultiline((string) ($message['message'] ?? ''));

        $lines = [
            'Bonjour,',
            '',
            'Un nouveau message a été envoyé depuis le formulaire de contact.',
            '',
            'Nom complet : ' . $name,
            'Adresse électronique : ' . $email,
            'Téléphone : ' . ($phone !== '' ? $phone : 'Non renseigné'),
            'Objet : ' . $subject,
            '',
            'Message :',
            $body,
            '',
            'Ce message a été enregistré dans l’administration.',
            'Date d’envoi : ' . Time::now('Africa/Bujumbura')->format('d/m/Y H:i'),
        ];

        return implode(PHP_EOL, $lines);
    }

    private function cleanSingleLine(string $value): string
    {
        $value = trim($value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function cleanMultiline(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", trim($value));

        return preg_replace("/[ \t]+/u", ' ', $value) ?? $value;
    }
}
