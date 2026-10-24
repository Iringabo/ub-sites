<?php

namespace App\Services;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Optional, cookieless audience measurement (self-hosted Matomo or Plausible).
 * Off unless a provider, an HTTPS instance URL and a site id are all set.
 */
class AnalyticsService
{
    /**
     * @var array<string, string>
     */
    public const PROVIDERS = [
        'none'      => 'Désactivée',
        'matomo'    => 'Matomo (sans cookie)',
        'plausible' => 'Plausible',
    ];

    /**
     * @param array<string, mixed>|null $settings
     *
     * @return array{provider: string, url: string, site_id: string}|null
     */
    public function config(?array $settings = null): ?array
    {
        $settings ??= service('settingsService')->all();
        $provider = strtolower(trim((string) ($settings['analytics.provider'] ?? 'none')));
        $url = rtrim(trim((string) ($settings['analytics.url'] ?? '')), '/');
        $siteId = trim((string) ($settings['analytics.site_id'] ?? ''));

        if (! in_array($provider, ['matomo', 'plausible'], true) || $siteId === '' || $this->origin($url) === null) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9._-]{1,120}$/', $siteId) !== 1) {
            return null;
        }

        return ['provider' => $provider, 'url' => $url . '/', 'site_id' => $siteId];
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public function isEnabled(?array $settings = null): bool
    {
        return $this->config($settings) !== null;
    }

    /**
     * Allows the configured instance in this response's CSP only.
     *
     * @param array{provider: string, url: string, site_id: string} $config
     */
    public function allowInCsp(array $config, ?ResponseInterface $response = null): void
    {
        $origin = $this->origin($config['url']);
        if ($origin === null) {
            return;
        }

        $response ??= service('response');
        $csp = $response->getCSP();
        $csp->addScriptSrc($origin);
        $csp->addScriptSrcElem($origin);
        $csp->addConnectSrc($origin);
        $csp->addImageSrc($origin);
    }

    public function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return 'https://' . strtolower((string) $parts['host']) . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
    }
}
