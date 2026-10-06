<?php

namespace App\Services;

use Throwable;

/**
 * Indexable language versions: the site default locale lives at the bare URL,
 * the other locale at the same URL with `?lang=xx`.
 */
class LocaleUrlService
{
    /**
     * @var list<string>
     */
    public const LOCALES = ['fr', 'en'];

    public function defaultLocale(): string
    {
        try {
            $locale = strtolower((string) (service('siteResolver')->activeSite()->default_locale ?? 'fr'));
        } catch (Throwable) {
            $locale = 'fr';
        }

        return in_array($locale, self::LOCALES, true) ? $locale : 'fr';
    }

    /**
     * Canonical URL of a page in one locale (no other query parameters).
     */
    public function canonical(?string $locale = null, ?string $path = null): string
    {
        $locale ??= site_current_locale();
        $url = $this->baseUrl($path);

        return $locale === $this->defaultLocale() ? $url : $url . '?lang=' . rawurlencode($locale);
    }

    /**
     * hreflang alternates for the current page, including x-default.
     *
     * @return array<string, string>
     */
    public function alternates(?string $path = null): array
    {
        $alternates = [];
        foreach (self::LOCALES as $locale) {
            $alternates[$locale] = $this->canonical($locale, $path);
        }
        $alternates['x-default'] = $this->canonical($this->defaultLocale(), $path);

        return $alternates;
    }

    /**
     * Link for the language switcher: keeps the current filters and always
     * carries `lang`, so it also overrides a previously chosen language.
     */
    public function switchUrl(string $locale): string
    {
        $query = service('request')->getGet();
        $query = is_array($query) ? $query : [];
        unset($query['lang']);
        $query['lang'] = $locale;

        return $this->baseUrl() . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function baseUrl(?string $path = null): string
    {
        $path ??= uri_string();
        $path = trim($path, '/');

        return $path === '' ? site_url('/') : site_url($path);
    }
}
