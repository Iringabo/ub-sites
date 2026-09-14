<?php

namespace App\Filters;

use App\Support\InstanceCookieNames;
use App\Support\TrustedProxies;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class LocaleFilter implements FilterInterface
{
    /**
     * @var list<string>
     */
    private array $supportedLocales = ['fr', 'en'];

    /**
     * @var list<string>
     */
    private array $englishCountryCodes = [
        'AG',
        'AU',
        'BS',
        'BB',
        'BZ',
        'BW',
        'CA',
        'DM',
        'FJ',
        'GH',
        'GB',
        'GD',
        'GY',
        'IE',
        'JM',
        'KE',
        'LS',
        'LR',
        'MT',
        'MU',
        'NZ',
        'NG',
        'PH',
        'RW',
        'SC',
        'SL',
        'SG',
        'ZA',
        'SS',
        'SZ',
        'TZ',
        'TT',
        'UG',
        'US',
        'ZM',
        'ZW',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $locale = $this->isFrenchOnlyArea($request)
            ? 'fr'
            : $this->detectLocale($request);

        $request->setLocale($locale);
        Services::language()->setLocale($locale);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function detectLocale(RequestInterface $request): string
    {
        $queryLocale = $this->normalizeLocale((string) $request->getGet('lang'));
        if ($queryLocale !== null) {
            service('response')->setCookie($this->cookieName(), $queryLocale, YEAR, '', '/', '', null, true, 'Lax');

            return $queryLocale;
        }

        $cookieLocale = $this->normalizeLocale((string) $request->getCookie($this->cookieName()));
        if ($cookieLocale !== null) {
            return $cookieLocale;
        }

        $countryLocale = $this->localeFromCountry($request);
        if ($countryLocale !== null) {
            return $countryLocale;
        }

        return $this->localeFromBrowser($request) ?? $this->siteDefaultLocale();
    }

    private function cookieName(): string
    {
        return InstanceCookieNames::locale();
    }

    private function isFrenchOnlyArea(RequestInterface $request): bool
    {
        $path = trim((string) $request->getUri()->getPath(), '/');

        return $path === 'login'
            || $path === 'logout'
            || $path === 'healthz' // sonde de santé : aucune dépendance au site résolu
            || str_starts_with($path, 'admin');
    }

    private function normalizeLocale(string $locale): ?string
    {
        $locale = strtolower(trim($locale));
        $locale = str_replace('_', '-', $locale);
        $locale = explode('-', $locale)[0] ?? $locale;

        return in_array($locale, $this->supportedLocales, true) ? $locale : null;
    }

    private function localeFromCountry(RequestInterface $request): ?string
    {
        if (! TrustedProxies::isConfigured()) {
            return null;
        }

        foreach (['CF-IPCountry', 'X-Vercel-IP-Country', 'CloudFront-Viewer-Country'] as $header) {
            $country = strtoupper(trim($request->getHeaderLine($header)));

            if ($country !== '') {
                return in_array($country, $this->englishCountryCodes, true) ? 'en' : null;
            }
        }

        return null;
    }

    private function localeFromBrowser(RequestInterface $request): ?string
    {
        $header = trim($request->getHeaderLine('Accept-Language'));
        if ($header === '') {
            return null;
        }

        $candidates = [];

        foreach (explode(',', $header) as $position => $part) {
            $segments = array_map('trim', explode(';', $part));
            $locale = $this->normalizeLocale($segments[0] ?? '');

            if ($locale === null) {
                continue;
            }

            $quality = 1.0;
            if (isset($segments[1]) && preg_match('/^q=([0-9.]+)$/', $segments[1], $matches) === 1) {
                $quality = max(0.0, min(1.0, (float) $matches[1]));
            }

            $candidates[] = [
                'locale'   => $locale,
                'quality'  => $quality,
                'position' => $position,
            ];
        }

        usort(
            $candidates,
            static fn (array $a, array $b): int => $b['quality'] <=> $a['quality'] ?: $a['position'] <=> $b['position'],
        );

        return $candidates[0]['locale'] ?? null;
    }

    private function siteDefaultLocale(): string
    {
        try {
            $locale = $this->normalizeLocale((string) (service('siteResolver')->activeSite()->default_locale ?? 'fr'));

            return $locale ?? 'fr';
        } catch (\Throwable) {
            return 'fr';
        }
    }
}
