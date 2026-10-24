<?php

namespace App\Support;

final class InstanceCookieNames
{
    public static function session(): string
    {
        return self::valid(env('session.cookieName'))
            ?? (self::isCentral() ? 'ci_session_central' : 'ci_session_' . self::slug());
    }

    public static function remember(): string
    {
        return self::valid(env('session.rememberCookieName'))
            ?? (self::isCentral() ? 'remember_central' : 'remember_' . self::slug());
    }

    public static function locale(): string
    {
        return self::isCentral() ? 'site_locale_central' : 'site_locale_' . self::slug();
    }

    public static function isCentral(): bool
    {
        return filter_var((string) env('app.centralAdminMode', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function slug(): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '', strtolower((string) env('app.siteSlug', ''))) ?? '';

        return $slug !== '' ? $slug : 'app';
    }

    public static function valid(mixed $value): ?string
    {
        $name = trim((string) $value);
        if ($name !== '' && preg_match('/^[0-9a-z_-]+$/', $name) === 1) {
            return $name;
        }

        return null;
    }
}
