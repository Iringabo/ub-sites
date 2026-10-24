<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class SecurityReviewTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    public function testPublicPagesSendSecurityHeaders(): void
    {
        $result = $this->get('/login');

        $result->assertOK();

        $response = $result->response();
        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('same-origin', $response->getHeaderLine('Referrer-Policy'));
        $this->assertSame('none', $response->getHeaderLine('X-Permitted-Cross-Domain-Policies'));
    }

    public function testErrorPagesAreFrenchAndDoNotExposeTraceData(): void
    {
        $error400 = view('errors/html/error_400', ['message' => 'Message technique']);
        $this->assertStringContainsString('lang="fr"', $error400);
        $this->assertStringContainsString('Requête incorrecte', $error400);
        $this->assertStringContainsString('Message technique', $error400);

        $production = view('errors/html/production', ['message' => 'Erreur technique']);
        $this->assertStringContainsString('lang="fr"', $production);
        $this->assertStringContainsString('Erreur 500', $production);
        $this->assertStringContainsString('Oups !', $production);
        $this->assertStringContainsString('Erreur technique', $production);
        $this->assertStringNotContainsString('Backtrace', $production);
        $this->assertStringNotContainsString('Stack trace', $production);
    }
}
