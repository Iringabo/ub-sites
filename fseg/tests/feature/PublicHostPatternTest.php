<?php

use App\Commands\CreateFacultySite;
use App\Database\Seeds\TemplateStarterSeeder;
use App\Services\FacultySiteProvisioningService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class PublicHostPatternTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    protected function tearDown(): void
    {
        $this->clearPublicHostPattern();
        parent::tearDown();
    }

    public function testHostnamesForSlugUsesPatternAndDoesNotHardcodeTest(): void
    {
        $service = new FacultySiteProvisioningService();

        $this->clearPublicHostPattern();
        $this->assertSame([], $service->hostnamesForSlug('fseg'));

        $this->setPublicHostPattern('{slug}.example.edu');
        $this->assertSame(['fseg.example.edu'], $service->hostnamesForSlug('fseg'));
        $this->assertSame(['fsi.example.edu'], $service->hostnamesForSlug('fsi'));

        $this->setPublicHostPattern('{slug}.account.alwaysdata.net, www.{slug}.account.alwaysdata.net');
        $this->assertSame(
            ['fseg.account.alwaysdata.net', 'www.fseg.account.alwaysdata.net'],
            $service->hostnamesForSlug('fseg'),
        );
    }

    public function testCreateFacultyStoresPatternHostsAndOmitsTestDefault(): void
    {
        $service = new FacultySiteProvisioningService();
        $slug    = 'hostpat' . substr(bin2hex(random_bytes(2)), 0, 4);

        $this->clearPublicHostPattern();
        $emptyId = $service->createFaculty([
            'identifier' => $slug . 'a',
            'slug'       => $slug . 'a',
            'name'       => 'Faculté pattern vide',
        ], null, false);
        $empty = $this->db->table('sites')->where('id', $emptyId)->get()->getRowArray();
        $this->assertIsArray($empty);
        $hosts = json_decode((string) $empty['hostnames'], true);
        $this->assertTrue($hosts === null || $hosts === [], 'empty pattern must not invent .test hosts');
        $this->assertStringNotContainsString('.test', (string) $empty['hostnames']);

        $this->setPublicHostPattern('{slug}.example.edu');
        $patternId = $service->createFaculty([
            'identifier' => $slug . 'b',
            'slug'       => $slug . 'b',
            'name'       => 'Faculté pattern edu',
        ], null, false);
        $pattern = $this->db->table('sites')->where('id', $patternId)->get()->getRowArray();
        $this->assertIsArray($pattern);
        $this->assertSame(
            [$slug . 'b.example.edu'],
            json_decode((string) $pattern['hostnames'], true),
        );
    }

    public function testSiteCreateCommandUsesHostnameOptionAndUpdatesExisting(): void
    {
        $slug = 'hostcli' . substr(bin2hex(random_bytes(2)), 0, 4);
        $this->clearPublicHostPattern();

        $command = new CreateFacultySite(service('logger'), service('commands'));
        $created = $command->run([
            'identifier' => $slug,
            'slug'       => $slug,
            'name'       => 'Faculté CLI hôte',
            'hostname'   => $slug . '.alwaysdata.net',
        ]);
        $this->assertSame(EXIT_SUCCESS, $created);

        $row = $this->db->table('sites')->where('slug', $slug)->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame(
            [$slug . '.alwaysdata.net'],
            json_decode((string) $row['hostnames'], true),
        );

        $this->setPublicHostPattern('{slug}.example.edu');
        $updated = $command->run([
            'identifier' => $slug,
            'slug'       => $slug,
            'name'       => 'Faculté CLI hôte',
        ]);
        $this->assertSame(EXIT_SUCCESS, $updated);

        $row = $this->db->table('sites')->where('slug', $slug)->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame(
            [$slug . '.example.edu'],
            json_decode((string) $row['hostnames'], true),
            're-running site:create with a pattern must replace the previous host',
        );
    }

    private function setPublicHostPattern(string $pattern): void
    {
        putenv('app.publicHostPattern=' . $pattern);
        $_ENV['app.publicHostPattern']    = $pattern;
        $_SERVER['app.publicHostPattern'] = $pattern;
    }

    private function clearPublicHostPattern(): void
    {
        putenv('app.publicHostPattern');
        unset($_ENV['app.publicHostPattern'], $_SERVER['app.publicHostPattern']);
    }
}
