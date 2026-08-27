<?php

namespace App\Controllers;

use App\Models\ContactMessageModel;
use App\Models\PageModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\HTTP\RedirectResponse;
use Config\Honeypot;
use Throwable;

class ContactController extends BaseController
{
    private const RATE_LIMIT_SECONDS = 120;

    private const IP_RATE_WINDOW_SECONDS = 3600;

    private const IP_RATE_MAX_MESSAGES = 20;

    public function new(): string
    {
        $page    = model(PageModel::class, false)->forSite()->where('key', 'contact')->where('is_published', 1)->first();
        $page    = $page === null ? null : service('contentTranslationService')->record('pages', $page);
        $content = $page !== null ? service('contentTranslationService')->pageContent($page, []) : [];

        return view('contact/new', [
            'title'        => site_text_or_placeholder($page?->seo_title ?? null, lang('Site.pages.contact')),
            'description'  => site_text_or_placeholder($page?->seo_description ?? null, lang('Site.contact.defaultDescription')),
            'activePage'   => 'contact',
            'pageTitle'    => lang('Site.pageTitles.contact'),
            'siteSettings' => $this->siteSettings,
            'content'      => $content,
            'limits'       => $this->contactLimits(),
        ]);
    }

    public function create(): RedirectResponse
    {
        $limits = $this->contactLimits();
        $payload = $this->contactPayload();

        if ($this->isHoneypotFilled($limits['honeypotField'])) {
            return redirect()->to('/contact')->with('message', lang('Site.contact.received'));
        }

        $rules = [
            'name'    => 'required|max_length[' . $limits['nameMaxLength'] . ']',
            'email'   => 'required|valid_email|max_length[' . $limits['emailMaxLength'] . ']',
            'phone'   => 'permit_empty|max_length[' . $limits['phoneMaxLength'] . ']',
            'subject' => 'required|max_length[' . $limits['subjectMaxLength'] . ']',
            'message' => 'required|min_length[10]|max_length[' . $limits['messageMaxLength'] . ']',
        ];

        $messages = [
            'name' => [
                'required'   => lang('Site.contact.validation.nameRequired'),
                'max_length' => lang('Site.contact.validation.nameMax', [$limits['nameMaxLength']]),
            ],
            'email' => [
                'required'    => lang('Site.contact.validation.emailRequired'),
                'valid_email' => lang('Site.contact.validation.emailValid'),
                'max_length'  => lang('Site.contact.validation.emailMax', [$limits['emailMaxLength']]),
            ],
            'phone' => [
                'max_length' => lang('Site.contact.validation.phoneMax', [$limits['phoneMaxLength']]),
            ],
            'subject' => [
                'required'   => lang('Site.contact.validation.subjectRequired'),
                'max_length' => lang('Site.contact.validation.subjectMax', [$limits['subjectMaxLength']]),
            ],
            'message' => [
                'required'   => lang('Site.contact.validation.messageRequired'),
                'min_length' => lang('Site.contact.validation.messageMin'),
                'max_length' => lang('Site.contact.validation.messageMax', [$limits['messageMaxLength']]),
            ],
        ];

        if (! $this->validateData($payload, $rules, $messages)) {
            return redirect()
                ->to('/contact')
                ->withInput()
                ->with('errors', $this->validator?->getErrors() ?? []);
        }

        if ($this->isRateLimited((string) ($payload['email'] ?? '')) || $this->isRateLimitedByIp($payload['ip_address'])) {
            return redirect()
                ->to('/contact')
                ->withInput()
                ->with('error', lang('Site.contact.rateLimited'));
        }

        $saved = model(ContactMessageModel::class, false)->insert([
            'name'         => $payload['name'],
            'email'        => $payload['email'],
            'phone'        => $payload['phone'],
            'subject'      => $payload['subject'],
            'message'      => $payload['message'],
            'status'       => 'new',
            'ip_address'   => $payload['ip_address'],
            'user_agent'   => $payload['user_agent'],
            'read_at'      => null,
            'processed_by' => null,
        ]);

        if ($saved === false) {
            return redirect()
                ->to('/contact')
                ->withInput()
                ->with('error', lang('Site.contact.saveFailed'));
        }

        try {
            service('contactNotificationService')->notify($payload);
        } catch (Throwable) {
            log_message('error', 'Une erreur inattendue a empêché la notification du message de contact.');
        }

        return redirect()
            ->to('/contact')
            ->with('message', lang('Site.contact.sent'));
    }

    /**
     * @return array{honeypotField: string, nameMaxLength: int, emailMaxLength: int, phoneMaxLength: int, subjectMaxLength: int, messageMaxLength: int}
     */
    private function contactLimits(): array
    {
        return [
            'honeypotField'  => config(Honeypot::class)->name,
            'nameMaxLength'  => 255,
            'emailMaxLength' => 255,
            'phoneMaxLength' => 80,
            'subjectMaxLength' => 255,
            'messageMaxLength' => 5000,
        ];
    }

    /**
     * @return array{name: string, email: string, phone: ?string, subject: string, message: string, ip_address: ?string, user_agent: ?string}
     */
    private function contactPayload(): array
    {
        $name    = $this->normalizeSingleLine((string) $this->request->getPost('name'));
        $email   = $this->normalizeSingleLine((string) $this->request->getPost('email'));
        $phone   = $this->normalizeSingleLine((string) $this->request->getPost('phone'));
        $subject = $this->normalizeSingleLine((string) $this->request->getPost('subject'));
        $message = $this->normalizeMessage((string) $this->request->getPost('message'));
        $agent   = $this->request->getUserAgent()?->getAgentString();

        return [
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone !== '' ? $phone : null,
            'subject'    => $subject,
            'message'    => $message,
            'ip_address' => $this->request->getIPAddress() ?: null,
            'user_agent' => $agent !== null ? mb_substr($agent, 0, 500) : null,
        ];
    }

    private function isHoneypotFilled(string $field): bool
    {
        $value = trim((string) $this->request->getPost($field));

        return $value !== '';
    }

    private function isRateLimited(string $email): bool
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return false;
        }

        $threshold = Time::now()->subSeconds(self::RATE_LIMIT_SECONDS)->toDateTimeString();

        return model(ContactMessageModel::class, false)
            ->forSite()
            ->where('LOWER(email)', $email)
            ->where('created_at >=', $threshold)
            ->countAllResults() > 0;
    }

    /**
     * Limite additionnelle par adresse IP : un expéditeur peut changer
     * d'adresse électronique, pas facilement d'adresse IP.
     */
    private function isRateLimitedByIp(?string $ip): bool
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return false;
        }

        $threshold = Time::now()->subSeconds(self::IP_RATE_WINDOW_SECONDS)->toDateTimeString();

        return model(ContactMessageModel::class, false)
            ->forSite()
            ->where('ip_address', $ip)
            ->where('created_at >=', $threshold)
            ->countAllResults() >= self::IP_RATE_MAX_MESSAGES;
    }

    private function normalizeSingleLine(string $value): string
    {
        $value = trim($value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function normalizeMessage(string $value): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));

        return preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
    }
}
