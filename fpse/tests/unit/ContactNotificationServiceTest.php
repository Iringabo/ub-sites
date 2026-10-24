<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockEmail;
use CodeIgniter\Test\TestLogger;
use Config\Services;

/**
 * @internal
 */
final class ContactNotificationServiceTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        $this->clearNotificationEnvironment();
        Services::reset();

        parent::tearDown();
    }

    public function testNotificationIsSkippedWhenDisabled(): void
    {
        $email = $this->emailSpy(true);
        Services::injectMock('email', $email);
        $this->setNotificationEnvironment([
            'CONTACT_NOTIFICATION_ENABLED'     => 'false',
            'CONTACT_NOTIFICATION_RECIPIENT'   => 'contact@example.test',
            'CONTACT_NOTIFICATION_FROM_EMAIL'   => 'no-reply@example.test',
            'CONTACT_NOTIFICATION_FROM_NAME'    => 'FSEG',
            'CONTACT_NOTIFICATION_PROTOCOL'     => 'smtp',
            'CONTACT_NOTIFICATION_SMTP_HOST'    => 'smtp.example.test',
            'CONTACT_NOTIFICATION_SMTP_PORT'    => '587',
            'CONTACT_NOTIFICATION_SMTP_CRYPTO'  => 'tls',
            'CONTACT_NOTIFICATION_SMTP_USER'    => 'smtp-user',
            'CONTACT_NOTIFICATION_SMTP_PASS'    => 'smtp-pass',
        ]);

        $result = service('contactNotificationService')->notify($this->messagePayload());

        $this->assertTrue($result);
        $this->assertSame(0, $email->sendCalls);
    }

    public function testNotificationIsSentWhenEnabled(): void
    {
        $email = $this->emailSpy(true);
        Services::injectMock('email', $email);
        $this->setNotificationEnvironment([
            'CONTACT_NOTIFICATION_ENABLED'     => 'true',
            'CONTACT_NOTIFICATION_RECIPIENT'   => 'contact@example.test',
            'CONTACT_NOTIFICATION_FROM_EMAIL'   => 'no-reply@example.test',
            'CONTACT_NOTIFICATION_FROM_NAME'    => 'FSEG',
            'CONTACT_NOTIFICATION_PROTOCOL'     => 'smtp',
            'CONTACT_NOTIFICATION_SMTP_HOST'    => 'smtp.example.test',
            'CONTACT_NOTIFICATION_SMTP_PORT'    => '587',
            'CONTACT_NOTIFICATION_SMTP_CRYPTO'  => 'tls',
            'CONTACT_NOTIFICATION_SMTP_USER'    => 'smtp-user',
            'CONTACT_NOTIFICATION_SMTP_PASS'    => 'smtp-pass',
        ]);

        $result = service('contactNotificationService')->notify($this->messagePayload());

        $this->assertTrue($result);
        $this->assertSame(1, $email->sendCalls);
    }

    public function testNotificationFailureIsLoggedWithoutBlocking(): void
    {
        $email = $this->emailSpy(false);
        Services::injectMock('email', $email);
        $this->setNotificationEnvironment([
            'CONTACT_NOTIFICATION_ENABLED'     => 'true',
            'CONTACT_NOTIFICATION_RECIPIENT'   => 'contact@example.test',
            'CONTACT_NOTIFICATION_FROM_EMAIL'   => 'no-reply@example.test',
            'CONTACT_NOTIFICATION_FROM_NAME'    => 'FSEG',
            'CONTACT_NOTIFICATION_PROTOCOL'     => 'smtp',
            'CONTACT_NOTIFICATION_SMTP_HOST'    => 'smtp.example.test',
            'CONTACT_NOTIFICATION_SMTP_PORT'    => '587',
            'CONTACT_NOTIFICATION_SMTP_CRYPTO'  => 'tls',
            'CONTACT_NOTIFICATION_SMTP_USER'    => 'smtp-user',
            'CONTACT_NOTIFICATION_SMTP_PASS'    => 'smtp-pass',
        ]);

        $result = service('contactNotificationService')->notify($this->messagePayload());

        $this->assertFalse($result);
        $this->assertSame(1, $email->sendCalls);
        $this->assertLogged('error', 'L’envoi de la notification du message de contact a échoué.');
    }

    public function testInvalidRecipientIsRejected(): void
    {
        $email = $this->emailSpy(true);
        Services::injectMock('email', $email);
        $this->setNotificationEnvironment([
            'CONTACT_NOTIFICATION_ENABLED'     => 'true',
            'CONTACT_NOTIFICATION_RECIPIENT'   => 'destinataire-invalide',
            'CONTACT_NOTIFICATION_FROM_EMAIL'   => 'no-reply@example.test',
            'CONTACT_NOTIFICATION_FROM_NAME'    => 'FSEG',
            'CONTACT_NOTIFICATION_PROTOCOL'     => 'smtp',
            'CONTACT_NOTIFICATION_SMTP_HOST'    => 'smtp.example.test',
            'CONTACT_NOTIFICATION_SMTP_PORT'    => '587',
            'CONTACT_NOTIFICATION_SMTP_CRYPTO'  => 'tls',
            'CONTACT_NOTIFICATION_SMTP_USER'    => 'smtp-user',
            'CONTACT_NOTIFICATION_SMTP_PASS'    => 'smtp-pass',
        ]);

        $result = service('contactNotificationService')->notify($this->messagePayload());

        $this->assertFalse($result);
        $this->assertSame(0, $email->sendCalls);
        $this->assertLogged('error', 'La notification du message de contact a été ignorée: destinataire invalide.');
    }

    public function testLogsDoNotContainSecrets(): void
    {
        $secretUser = 'smtp-secret-user';
        $secretPass = 'smtp-secret-pass';

        $email = $this->emailSpy(false);
        Services::injectMock('email', $email);
        $this->setNotificationEnvironment([
            'CONTACT_NOTIFICATION_ENABLED'     => 'true',
            'CONTACT_NOTIFICATION_RECIPIENT'   => 'contact@example.test',
            'CONTACT_NOTIFICATION_FROM_EMAIL'   => 'no-reply@example.test',
            'CONTACT_NOTIFICATION_FROM_NAME'    => 'FSEG',
            'CONTACT_NOTIFICATION_PROTOCOL'     => 'smtp',
            'CONTACT_NOTIFICATION_SMTP_HOST'    => 'smtp.example.test',
            'CONTACT_NOTIFICATION_SMTP_PORT'    => '587',
            'CONTACT_NOTIFICATION_SMTP_CRYPTO'  => 'tls',
            'CONTACT_NOTIFICATION_SMTP_USER'    => $secretUser,
            'CONTACT_NOTIFICATION_SMTP_PASS'    => $secretPass,
        ]);

        $result = service('contactNotificationService')->notify($this->messagePayload());

        $this->assertFalse($result);
        $this->assertFalse(TestLogger::didLog('error', $secretUser, false));
        $this->assertFalse(TestLogger::didLog('error', $secretPass, false));
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function messagePayload(array $overrides = []): array
    {
        return array_replace([
            'name'    => 'Marie Dupont',
            'email'   => 'marie.dupont@example.test',
            'phone'   => '0123 456 789',
            'subject' => 'Demande de contact',
            'message' => "Bonjour,\nJe vous contacte pour un test.\nMerci.",
        ], $overrides);
    }

    /**
     * @param array<string, string> $values
     */
    private function setNotificationEnvironment(array $values): void
    {
        foreach ($values as $key => $value) {
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    private function clearNotificationEnvironment(): void
    {
        foreach ([
            'CONTACT_NOTIFICATION_ENABLED',
            'CONTACT_NOTIFICATION_RECIPIENT',
            'CONTACT_NOTIFICATION_FROM_EMAIL',
            'CONTACT_NOTIFICATION_FROM_NAME',
            'CONTACT_NOTIFICATION_PROTOCOL',
            'CONTACT_NOTIFICATION_SMTP_HOST',
            'CONTACT_NOTIFICATION_SMTP_PORT',
            'CONTACT_NOTIFICATION_SMTP_CRYPTO',
            'CONTACT_NOTIFICATION_SMTP_USER',
            'CONTACT_NOTIFICATION_SMTP_PASS',
        ] as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
    }

    private function emailSpy(bool $sendResult): MockEmail
    {
        $email = new class (config(\Config\Email::class)) extends MockEmail {
            public int $sendCalls = 0;

            public function send($autoClear = true)
            {
                $this->sendCalls++;

                return parent::send($autoClear);
            }
        };

        $email->returnValue = $sendResult;

        return $email;
    }
}
