<?php

use CodeIgniter\I18n\Time;

if (! function_exists('site_current_locale')) {
    function site_current_locale(): string
    {
        $locale = service('contentTranslationService')->locale(service('request')->getLocale());

        return $locale !== '' ? $locale : 'fr';
    }
}

if (! function_exists('site_current_site')) {
    function site_current_site(): object
    {
        return service('siteResolver')->activeSite();
    }
}

if (! function_exists('site_current_site_id')) {
    function site_current_site_id(): int
    {
        return service('siteResolver')->activeSiteId();
    }
}

if (! function_exists('site_og_locale')) {
    function site_og_locale(): string
    {
        return site_current_locale() === 'en' ? 'en_US' : 'fr_FR';
    }
}

if (! function_exists('site_current_url_for_redirect')) {
    function site_current_url_for_redirect(): string
    {
        $uri = uri_string();
        $url = $uri === '' ? site_url('/') : site_url($uri);
        $query = trim((string) service('request')->getServer('QUERY_STRING'));

        return $query === '' ? $url : $url . '?' . $query;
    }
}

if (! function_exists('site_media_url')) {
    function site_media_url(?string $path, string $fallback = 'assets/images/logo-placeholder.png'): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            $path = $fallback;
        }

        if (preg_match('#^(https?:)?//#', $path) === 1) {
            return $path;
        }

        return base_url(ltrim($path, '/'));
    }
}

if (! function_exists('site_public_url')) {
    function site_public_url(?string $url, string $fallback = '/'): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            $url = $fallback;
        }

        if (
            str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:')
            || str_starts_with($url, '#')
        ) {
            return $url;
        }

        return site_url(ltrim($url, '/'));
    }
}

if (! function_exists('site_css_url')) {
    function site_css_url(?string $path, string $fallback = 'assets/images/logo-placeholder.png'): string
    {
        $url = site_media_url($path, $fallback);

        return str_replace(
            ["\\", "'", "\n", "\r", "\f"],
            ["\\\\", "\\'", ' ', ' ', ' '],
            $url,
        );
    }
}

if (! function_exists('site_paragraphs')) {
    /**
     * @return list<string>
     */
    function site_paragraphs(?string $text): array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\R{2,}/u', $text) ?: [],
            static fn (string $paragraph): bool => trim($paragraph) !== '',
        ));
    }
}

if (! function_exists('site_format_date')) {
    function site_format_date(mixed $value, bool $withTime = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof Time) {
            $timestamp = $value->getTimestamp();
        } elseif ($value instanceof DateTimeInterface) {
            $timestamp = $value->getTimestamp();
        } else {
            $timestamp = strtotime((string) $value);
        }

        if ($timestamp === false) {
            return '';
        }

        $locale = site_current_locale();
        $pattern = $withTime
            ? ($locale === 'en' ? 'd MMMM yyyy HH:mm' : 'd MMMM yyyy à HH:mm')
            : 'd MMMM yyyy';
        $format  = new IntlDateFormatter($locale === 'en' ? 'en_US' : 'fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::SHORT);
        $format->setPattern($pattern);
        $format->setTimeZone(app_timezone());

        return (string) $format->format($timestamp);
    }
}

if (! function_exists('site_level_label')) {
    function site_level_label(?string $level): string
    {
        return lang('Site.programmes.levelLabels.' . ($level ?: 'default'));
    }
}

if (! function_exists('site_staff_category_label')) {
    function site_staff_category_label(?string $category): string
    {
        return lang('Site.staff.categoryLabels.' . ($category ?: 'default'));
    }
}

if (! function_exists('site_post_type_label')) {
    function site_post_type_label(?string $type): string
    {
        return $type === 'event' ? lang('Site.posts.eventSingular') : lang('Site.posts.newsSingular');
    }
}

if (! function_exists('site_post_category')) {
    function site_post_category(?string $type): string
    {
        return $type === 'event' ? 'evenement' : 'actualite';
    }
}

if (! function_exists('site_post_status_label')) {
    function site_post_status_label(?string $status): string
    {
        return lang('Site.status.' . ($status ?: 'unknown'));
    }
}

if (! function_exists('site_message_status_label')) {
    function site_message_status_label(?string $status): string
    {
        return lang('Site.status.' . ($status ?: 'unknown'));
    }
}

if (! function_exists('site_message_status_badge_class')) {
    function site_message_status_badge_class(?string $status): string
    {
        return match ($status) {
            'new'      => 'text-bg-primary',
            'read'     => 'text-bg-info',
            'handled'  => 'text-bg-success',
            'archived' => 'text-bg-secondary',
            default    => 'text-bg-light',
        };
    }
}

if (! function_exists('site_datetime_input')) {
    function site_datetime_input(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i');
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
    }
}

if (! function_exists('site_decode_page_content')) {
    /**
     * @return array<string, mixed>
     */
    function site_decode_page_content(?string $content): array
    {
        $decoded = json_decode((string) $content, true);

        return is_array($decoded) ? $decoded : ['body' => $content];
    }
}

if (! function_exists('site_text_or_placeholder')) {
    function site_text_or_placeholder(?string $value, ?string $placeholder = null): string
    {
        $value = trim((string) $value);

        if ($value !== '') {
            return $value;
        }

        return $placeholder !== null ? $placeholder : lang('Home.configurationMissing');
    }
}

if (! function_exists('site_asset_url')) {
    /**
     * URL publique d'un actif local, versionnée par sa date de modification
     * (cache-navigateur invalidé automatiquement après chaque mise à jour).
     */
    function site_asset_url(string $path): string
    {
        $full = FCPATH . ltrim($path, '/');

        return base_url($path) . (is_file($full) ? '?v=' . filemtime($full) : '');
    }
}
