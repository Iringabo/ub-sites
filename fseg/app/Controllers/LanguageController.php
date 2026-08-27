<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class LanguageController extends BaseController
{
    /**
     * @var list<string>
     */
    private array $supportedLocales = ['fr', 'en'];

    public function set(string $locale): RedirectResponse
    {
        $locale = strtolower($locale);
        if (! in_array($locale, $this->supportedLocales, true)) {
            $locale = 'fr';
        }

        service('response')->setCookie('site_locale', $locale, YEAR, '', '/', '', null, true, 'Lax');

        return redirect()
            ->to($this->safeRedirect())
            ->withCookies();
    }

    private function safeRedirect(): string
    {
        $redirect = trim((string) $this->request->getGet('redirect'));

        if ($redirect === '') {
            return site_url('/');
        }

        // Conserver le slash final : compare contre « https://host/ » et non
        // « https://host », sinon « https://host.malveillant.example » passe.
        $baseUrl = site_url('/');

        if (str_starts_with($redirect, $baseUrl)) {
            return $redirect;
        }

        if (str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            return site_url(ltrim($redirect, '/'));
        }

        return site_url('/');
    }
}
