<?php

use App\Services\FacultyDemoDataService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class DemoPageContentShapeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $migrateOnce = true;
    protected $refresh     = true;

    public function testDemoSeedProducesLegacyCompatiblePageJson(): void
    {
        $siteId = (int) $this->db->table('sites')->insert([
            'identifier'       => 'fseg',
            'slug'             => 'fseg',
            'name'             => 'Faculté des Sciences Économiques et de Gestion',
            'status'           => 'active',
            'primary_color'    => '#0D9B49',
            'secondary_color'  => '#0B6F38',
            'enabled_sections' => json_encode(['hero']),
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ], true);

        $this->assertGreaterThan(0, $siteId);

        $result = (new FacultyDemoDataService($this->db))->seedSite($siteId, true);
        $this->assertSame('fseg', $result['slug']);

        $pages = $this->db->table('pages')->where('site_id', $siteId)->get()->getResultArray();
        $byKey = [];
        foreach ($pages as $page) {
            $byKey[$page['key']] = json_decode((string) $page['content'], true);
        }

        $this->assertArrayHasKey('faculty', $byKey);
        $faculty = $byKey['faculty'];
        $this->assertIsArray($faculty['dean']['paragraphs'] ?? null);
        $this->assertNotEmpty($faculty['dean']['paragraphs']);
        $this->assertNotEmpty($faculty['dean']['photo'] ?? null);
        $this->assertIsArray($faculty['mission'] ?? null);
        $this->assertIsArray($faculty['mission']['paragraphs'] ?? null);
        $this->assertIsArray($faculty['vision']['paragraphs'] ?? null);
        $this->assertIsArray($faculty['values'] ?? null);
        $this->assertArrayHasKey('description', $faculty['values'][0] ?? []);
        $this->assertArrayHasKey('history', $faculty);

        $this->assertArrayHasKey('formations', $byKey);
        $this->assertNotEmpty($byKey['formations']['offer_title'] ?? null);
        $this->assertNotEmpty($byKey['formations']['cta_label'] ?? null);

        $this->assertArrayHasKey('contact', $byKey);
        $this->assertNotEmpty($byKey['contact']['map_url'] ?? null);
        $this->assertNotEmpty($byKey['contact']['contact_title'] ?? null);

        $translations = $this->db->table('content_translations')->where('site_id', $siteId)->countAllResults();
        $this->assertGreaterThan(0, $translations);

        $highlights = $this->db->table('home_highlights')->where('site_id', $siteId)->countAllResults();
        $this->assertGreaterThanOrEqual(4, $highlights);

        // Palette and section order follow the per-faculty defaults.
        $site = $this->db->table('sites')->where('id', $siteId)->get()->getRowArray();
        $this->assertSame(FacultyDemoDataService::BRAND_COLORS['fseg'], $site['primary_color']);
        $this->assertSame(service('siteTheme')->tokens($site['primary_color'])['brand-dark'], $site['secondary_color']);
        $this->assertSame(FacultyDemoDataService::DEFAULT_SECTION_ORDER, json_decode((string) $site['enabled_sections'], true));

        $themeColor = $this->db->table('settings')->where('site_id', $siteId)->where('key', 'seo.theme_color')->get()->getRowArray();
        $this->assertSame($site['primary_color'], $themeColor['value'] ?? null);

        $home = $this->db->table('home_content')->where('site_id', $siteId)->get()->getRowArray();
        $this->assertStringEndsWith('· Université du Burundi', (string) $home['hero_badge']);

        $firstSlide = $this->db->table('home_hero_slides')->where('site_id', $siteId)->orderBy('display_order', 'ASC')->get()->getRowArray();
        $this->assertNotEmpty($firstSlide['primary_cta_label']);
        $this->assertNotEmpty($firstSlide['secondary_cta_label']);
        $this->assertNotSame('none', $firstSlide['secondary_cta_target']);

        $cta = $this->db->table('content_blocks')->where('site_id', $siteId)->where('type', 'contact_cta')->get()->getRowArray();
        $this->assertSame('/contact', json_decode((string) $cta['settings'], true)['url'] ?? null);
    }

    public function testEveryDemoFacultyColourIsAShadeOfTheLogo(): void
    {
        $theme = service('siteTheme');
        foreach (FacultyDemoDataService::BRAND_COLORS as $slug => $hex) {
            $tokens = $theme->tokens($hex);
            $isGreen = $tokens['accent'] === \App\Services\SiteThemeService::UB_RED;
            $isRed   = $tokens['accent'] === \App\Services\SiteThemeService::UB_GREEN;
            $this->assertTrue($isGreen || $isRed, $slug . ' must be a green or red shade');
        }
        $this->assertSame(\App\Services\SiteThemeService::UB_GREEN, (new FacultyDemoDataService($this->db))->brandColorForSlug('unknown-faculty'));
    }
}
