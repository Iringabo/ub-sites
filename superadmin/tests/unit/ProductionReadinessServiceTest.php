<?php

use App\Services\ProductionReadinessService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

/**
 * @internal
 */
final class ProductionReadinessServiceTest extends CIUnitTestCase
{
    public function testProductionProfileHasNoBlockingErrors(): void
    {
        $app = new App();
        $app->baseURL = 'https://fseg.example.bi/';
        $app->forceGlobalSecureRequests = true;
        $app->CSPEnabled = true;
        $app->proxyIPs = ['10.0.0.10' => 'X-Forwarded-For'];

        $service = new ProductionReadinessService();
        $checks = $service->checks($app, $this->productionDatabase(), $this->productionEnvironment());
        $statuses = $this->statusesById($checks);

        $this->assertFalse($service->hasBlockingIssues($checks));
        $this->assertFalse($service->hasBlockingIssues($checks, true), 'Les actifs frontaux étant auto-hébergés, le mode strict ne doit plus bloquer.');
        $this->assertSame('ok', $statuses['prod.environment']);
        $this->assertSame('ok', $statuses['prod.force_https']);
        $this->assertSame('ok', $statuses['prod.uploads_nginx']);
        $this->assertSame('ok', $statuses['prod.cdn_strategy']);
    }

    public function testDevelopmentDefaultsAreRejectedForProduction(): void
    {
        $app = new App();
        $app->baseURL = 'http://localhost:8080/';
        $app->forceGlobalSecureRequests = false;
        $app->CSPEnabled = false;
        $app->proxyIPs = [];

        $database = $this->productionDatabase();
        $database['password'] = 'your-database-password';
        $database['DBDebug'] = true;

        $service = new ProductionReadinessService();
        $checks = $service->checks($app, $database, [
            'CI_ENVIRONMENT' => 'development',
            'encryption.key' => '',
            'CI_DEBUG' => 'true',
            'CONTACT_NOTIFICATION_ENABLED' => 'true',
            'CONTACT_NOTIFICATION_RECIPIENT' => 'destinataire-invalide',
        ]);
        $statuses = $this->statusesById($checks);

        $this->assertTrue($service->hasBlockingIssues($checks));
        $this->assertSame('error', $statuses['prod.environment']);
        $this->assertSame('error', $statuses['prod.base_url']);
        $this->assertSame('error', $statuses['prod.force_https']);
        $this->assertSame('error', $statuses['prod.csp_enabled']);
        $this->assertSame('error', $statuses['prod.encryption_key']);
        $this->assertSame('error', $statuses['prod.database_credentials']);
        $this->assertSame('error', $statuses['prod.database_debug']);
        $this->assertSame('error', $statuses['prod.contact_email']);
        $this->assertSame('warning', $statuses['prod.proxy']);
    }

    /**
     * @return array<string, mixed>
     */
    private function productionDatabase(): array
    {
        return [
            'hostname' => '127.0.0.1',
            'database' => 'site_production',
            'username' => 'site_app',
            'password' => 'MotDePasseBaseTresLongEtUnique2026!',
            'DBDriver' => 'MySQLi',
            'charset'  => 'utf8mb4',
            'DBDebug'  => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productionEnvironment(): array
    {
        return [
            'CI_ENVIRONMENT' => 'production',
            'encryption.key' => 'cle-production-longue-et-unique-2026',
            'CI_DEBUG' => 'false',
            'CONTACT_NOTIFICATION_ENABLED' => 'true',
            'CONTACT_NOTIFICATION_RECIPIENT' => 'contact@example.bi',
            'CONTACT_NOTIFICATION_FROM_EMAIL' => 'no-reply@example.bi',
            'CONTACT_NOTIFICATION_PROTOCOL' => 'smtp',
            'CONTACT_NOTIFICATION_SMTP_HOST' => 'smtp.example.bi',
            'CONTACT_NOTIFICATION_SMTP_PORT' => '587',
            'CONTACT_NOTIFICATION_SMTP_CRYPTO' => 'tls',
            'CONTACT_NOTIFICATION_SMTP_USER' => 'smtp-user',
            'CONTACT_NOTIFICATION_SMTP_PASS' => 'MotDePasseSmtpTresLongEtUnique2026!',
        ];
    }

    /**
     * @param list<array{id: string, status: string, label: string, message: string}> $checks
     *
     * @return array<string, string>
     */
    private function statusesById(array $checks): array
    {
        $statuses = [];

        foreach ($checks as $check) {
            $statuses[$check['id']] = $check['status'];
        }

        return $statuses;
    }
}
