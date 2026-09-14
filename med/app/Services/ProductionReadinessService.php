<?php

namespace App\Services;

use Config\App;
use Config\ContentSecurityPolicy;
use Throwable;

final class ProductionReadinessService
{
    /**
     * @param array<string, mixed>|null $database
     * @param array<string, mixed>|null $env
     *
     * @return list<array{id: string, status: string, label: string, message: string}>
     */
    public function checks(?App $app = null, ?array $database = null, ?array $env = null): array
    {
        $app ??= config(App::class);
        $database ??= (array) config('Database')->default;
        $checks = [];

        $environment = $this->envString('CI_ENVIRONMENT', $env) ?? ENVIRONMENT;
        $this->add(
            $checks,
            'prod.environment',
            $environment === 'production' ? 'ok' : 'error',
            'Environnement',
            $environment === 'production'
                ? 'CI_ENVIRONMENT vaut production.'
                : 'CI_ENVIRONMENT doit valoir production avant livraison.',
        );

        $host = parse_url($app->baseURL, PHP_URL_HOST);
        $this->add(
            $checks,
            'prod.base_url',
            str_starts_with($app->baseURL, 'https://') && ! in_array($host, ['localhost', '127.0.0.1', 'example.com', 'example.test'], true) ? 'ok' : 'error',
            'URL publique',
            'app.baseURL doit être une URL HTTPS finale, pas une adresse locale ou de démonstration.',
        );

        $this->add(
            $checks,
            'prod.force_https',
            $app->forceGlobalSecureRequests ? 'ok' : 'error',
            'HTTPS forcé',
            $app->forceGlobalSecureRequests
                ? 'Les requêtes HTTP seront redirigées vers HTTPS.'
                : 'app.forceGlobalSecureRequests doit être activé en production.',
        );

        $this->add(
            $checks,
            'prod.csp_enabled',
            $app->CSPEnabled ? 'ok' : 'error',
            'Content Security Policy',
            $app->CSPEnabled
                ? 'La CSP est activée.'
                : 'app.CSPEnabled doit être activé en production.',
        );

        /** @var ContentSecurityPolicy $csp */
        $csp = config(ContentSecurityPolicy::class);
        $this->add(
            $checks,
            'prod.csp_policy',
            $csp->objectSrc === 'none' && $csp->frameAncestors === 'self' && $csp->scriptSrcAttr === 'none' ? 'ok' : 'error',
            'Politique CSP',
            'La CSP doit bloquer les objets, les scripts en attribut et le framing externe.',
        );

        $encryptionKey = $this->envString('encryption.key', $env);
        $this->add(
            $checks,
            'prod.encryption_key',
            $this->looksLikeSecret($encryptionKey) ? 'ok' : 'error',
            'Clé de chiffrement',
            'encryption.key doit contenir un secret réel d’au moins 32 caractères.',
        );

        $this->add(
            $checks,
            'prod.database_driver',
            ($database['DBDriver'] ?? '') === 'MySQLi' ? 'ok' : 'error',
            'Base de données',
            'Le pilote de production attendu est MySQLi pour MySQL/MariaDB.',
        );

        $this->add(
            $checks,
            'prod.database_charset',
            ($database['charset'] ?? '') === 'utf8mb4' ? 'ok' : 'error',
            'Encodage base',
            'La connexion de production doit utiliser utf8mb4.',
        );

        $databaseReady = $this->nonEmpty($database['hostname'] ?? null)
            && $this->nonEmpty($database['database'] ?? null)
            && $this->nonEmpty($database['username'] ?? null)
            && $this->looksLikeSecret($database['password'] ?? null);
        $this->add(
            $checks,
            'prod.database_credentials',
            $databaseReady ? 'ok' : 'error',
            'Identifiants base',
            'Les identifiants de base doivent être renseignés et ne pas utiliser de valeur de démonstration.',
        );

        $this->add(
            $checks,
            'prod.database_debug',
            empty($database['DBDebug']) ? 'ok' : 'error',
            'Erreurs SQL',
            empty($database['DBDebug'])
                ? 'DBDebug est désactivé.'
                : 'DBDebug doit être désactivé en production.',
        );

        $expectedDatabase = (string) ($database['database'] ?? '');
        try {
            $db = db_connect();
            $connectedDatabase = (string) $db->getDatabase();
            if ($expectedDatabase === '' || $connectedDatabase === $expectedDatabase) {
                if (! $db->tableExists('settings')) {
                    $this->add(
                        $checks,
                        'prod.settings_site_id',
                        'warning',
                        'Paramètres par faculté',
                        'Table settings introuvable. Exécutez php spark migrate --all.',
                    );
                } elseif ($db->fieldExists('site_id', 'settings')) {
                    $this->add(
                        $checks,
                        'prod.settings_site_id',
                        'ok',
                        'Paramètres par faculté',
                        'La table settings porte site_id (isolation par faculté).',
                    );
                } else {
                    $this->add(
                        $checks,
                        'prod.settings_site_id',
                        'error',
                        'Paramètres par faculté',
                        'La table settings n’a pas de colonne site_id. Appliquez les migrations de périmètre de site.',
                    );
                }
            }
        } catch (Throwable) {
            // La base cible n’est pas joignable depuis ce processus de contrôle.
        }

        $apacheUploads = $this->fileContains(FCPATH . 'uploads/.htaccess', ['Require all denied', 'RemoveHandler', 'php_flag engine off']);
        $this->add(
            $checks,
            'prod.uploads_apache',
            $apacheUploads ? 'ok' : 'error',
            'Uploads Apache',
            'public/uploads/.htaccess doit bloquer la navigation et l’exécution de scripts.',
        );

        $nginxUploads = $this->fileContains(ROOTPATH . 'deploy/nginx/uploads-security.conf', ['/uploads/', 'return 404', 'try_files $uri =404']);
        $this->add(
            $checks,
            'prod.uploads_nginx',
            $nginxUploads ? 'ok' : 'error',
            'Uploads Nginx',
            'deploy/nginx/uploads-security.conf doit être inclus dans le serveur Nginx.',
        );

        $debug = $this->envString('CI_DEBUG', $env);
        $this->add(
            $checks,
            'prod.debug',
            $debug === null || ! $this->truthy($debug) ? 'ok' : 'error',
            'Mode debug',
            'CI_DEBUG ne doit pas être activé en production.',
        );

        $this->add(
            $checks,
            'prod.proxy',
            $app->proxyIPs !== [] ? 'ok' : 'warning',
            'Proxy de confiance',
            $app->proxyIPs !== []
                ? 'Les proxys de confiance sont configurés.'
                : 'Configurer app.proxyIPs si le site est derrière un proxy ou un équilibreur.',
        );

        $this->add(
            $checks,
            'prod.contact_email',
            $this->contactEmailStatus($env),
            'Notification contact',
            'Si la notification est activée, destinataire, expéditeur et SMTP doivent être valides.',
        );

        $this->add(
            $checks,
            'prod.cdn_strategy',
            $this->usesCdnAssets() ? 'warning' : 'ok',
            'Dépendances CDN',
            $this->usesCdnAssets()
                ? 'Bootstrap, Bootstrap Icons ou Google Fonts dépendent encore de CDN ; documenter disponibilité, SRI/CSP ou hébergement local.'
                : 'Les actifs frontaux sont servis localement.',
        );

        return $checks;
    }

    /**
     * @param list<array{id: string, status: string, label: string, message: string}> $checks
     */
    public function hasBlockingIssues(array $checks, bool $strict = false): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] === 'error' || ($strict && $check['status'] === 'warning')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{id: string, status: string, label: string, message: string}> $checks
     */
    public function summary(array $checks): string
    {
        $counts = ['ok' => 0, 'warning' => 0, 'error' => 0];

        foreach ($checks as $check) {
            $counts[$check['status']]++;
        }

        return sprintf('%d OK, %d avertissement(s), %d erreur(s)', $counts['ok'], $counts['warning'], $counts['error']);
    }

    /**
     * @param list<array{id: string, status: string, label: string, message: string}> $checks
     */
    private function add(array &$checks, string $id, string $status, string $label, string $message): void
    {
        $checks[] = [
            'id'      => $id,
            'status'  => $status,
            'label'   => $label,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed>|null $env
     */
    private function envString(string $key, ?array $env): ?string
    {
        $value = $env[$key] ?? env($key);

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function nonEmpty(mixed $value): bool
    {
        return is_scalar($value) && trim((string) $value) !== '';
    }

    private function looksLikeSecret(mixed $value): bool
    {
        if (! $this->nonEmpty($value)) {
            return false;
        }

        $value = trim((string) $value);

        return strlen($value) >= 32
            && ! str_contains(strtolower($value), 'your-')
            && ! str_contains(strtolower($value), 'password')
            && ! str_contains(strtolower($value), 'example');
    }

    /**
     * @param list<string> $needles
     */
    private function fileContains(string $path, array $needles): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return false;
        }

        foreach ($needles as $needle) {
            if (! str_contains($content, $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed>|null $env
     */
    private function contactEmailStatus(?array $env): string
    {
        if (! $this->truthy($this->envString('CONTACT_NOTIFICATION_ENABLED', $env))) {
            return 'warning';
        }

        $recipient = $this->envString('CONTACT_NOTIFICATION_RECIPIENT', $env);
        $from      = $this->envString('CONTACT_NOTIFICATION_FROM_EMAIL', $env);
        $protocol  = $this->envString('CONTACT_NOTIFICATION_PROTOCOL', $env);
        $host      = $this->envString('CONTACT_NOTIFICATION_SMTP_HOST', $env);
        $port      = $this->envString('CONTACT_NOTIFICATION_SMTP_PORT', $env);
        $crypto    = $this->envString('CONTACT_NOTIFICATION_SMTP_CRYPTO', $env);
        $user      = $this->envString('CONTACT_NOTIFICATION_SMTP_USER', $env);
        $pass      = $this->envString('CONTACT_NOTIFICATION_SMTP_PASS', $env);

        $valid = filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false
            && filter_var($from, FILTER_VALIDATE_EMAIL) !== false
            && $protocol === 'smtp'
            && $this->nonEmpty($host)
            && ctype_digit((string) $port)
            && in_array($crypto, ['tls', 'ssl'], true)
            && $this->nonEmpty($user)
            && $this->looksLikeSecret($pass);

        return $valid ? 'ok' : 'error';
    }

    private function truthy(?string $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function usesCdnAssets(): bool
    {
        foreach ([
            APPPATH . 'Views/layouts/public.php',
            APPPATH . 'Views/layouts/admin.php',
            APPPATH . 'Views/auth/layout.php',
            APPPATH . 'Views/errors/html/production.php',
            APPPATH . 'Views/errors/html/error_404.php',
        ] as $path) {
            $content = is_file($path) ? file_get_contents($path) : false;

            if (is_string($content) && preg_match('#https://(?:cdn\.jsdelivr\.net|fonts\.googleapis\.com|fonts\.gstatic\.com)#', $content) === 1) {
                return true;
            }
        }

        return false;
    }
}
