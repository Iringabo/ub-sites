<?php

namespace App\Support;

use CodeIgniter\HTTP\RequestInterface;

/**
 * Headers de confiance partagés par la résolution de site public et
 * la détection d’hôte d’administration. X-Forwarded-Host n’est lu que
 * lorsque Config\App::$proxyIPs est renseigné.
 */
final class TrustedProxies
{
    public static function isConfigured(): bool
    {
        $proxyIPs = config('App')->proxyIPs ?? [];

        return is_array($proxyIPs) && $proxyIPs !== [];
    }

    public static function forwardedHost(RequestInterface $request): string
    {
        if (! self::isConfigured()) {
            return '';
        }

        $header = trim($request->getHeaderLine('X-Forwarded-Host'));
        if ($header === '') {
            return '';
        }

        return trim(explode(',', $header)[0] ?? '');
    }

    /**
     * Hôtes explicites de la requête : Host / HTTP_HOST, plus X-Forwarded-Host
     * si un proxy est déclaré. SERVER_NAME (nom vhost par défaut) est exclu.
     *
     * @return list<string>
     */
    public static function hostCandidates(RequestInterface $request): array
    {
        $candidates = [
            $request->getHeaderLine('Host'),
            $request->getHeaderLine('HTTP_HOST'),
            (string) ($request->getServer('HTTP_HOST') ?? ''),
            self::forwardedHost($request),
        ];

        $normalized = [];
        foreach ($candidates as $candidate) {
            $host = self::normalizedHost((string) $candidate);
            if ($host !== '' && ! in_array($host, $normalized, true)) {
                $normalized[] = $host;
            }
        }

        return $normalized;
    }

    public static function normalizedHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return trim($host, '.');
    }
}
