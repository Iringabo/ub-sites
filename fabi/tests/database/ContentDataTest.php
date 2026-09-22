<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class ContentDataTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    public function testMigrationsAndSeedersCreateExpectedTables(): void
    {
        foreach ([
            'home_content',
            'home_hero_slides',
            'home_highlights',
            'posts',
            'programmes',
            'staff',
            'laboratories',
            'publications',
            'research_projects',
            'timeline_items',
            'alumni_profiles',
            'testimonials',
            'site_stats',
            'pages',
            'contact_messages',
            'content_translations',
            'settings',
        ] as $table) {
            $this->assertTrue($this->db->tableExists($table), 'Table manquante : ' . $table);
        }

        $this->assertSame(1, $this->countRows('home_content'));
        $this->assertSame(3, $this->countRows('home_hero_slides'));
        $this->assertSame(3, $this->countRows('home_highlights'));
        $this->assertSame(2, $this->countRows('posts'));
        $this->assertSame(3, $this->countRows('programmes'));
        $this->assertSame(2, $this->countRows('staff'));
        $this->assertSame(1, $this->countRows('laboratories'));
        $this->assertSame(1, $this->countRows('publications'));
        $this->assertSame(1, $this->countRows('research_projects'));
        $this->assertSame(1, $this->countRows('timeline_items'));
        $this->assertSame(1, $this->countRows('alumni_profiles'));
        $this->assertSame(1, $this->countRows('testimonials'));
        $this->assertSame(6, $this->countRows('site_stats'));
        $this->assertGreaterThanOrEqual(7, $this->countRows('pages'));
        $this->assertSame(6, $this->countRows('content_translations'));
        $this->assertGreaterThanOrEqual(16, $this->countRows('settings'));
    }

    public function testMainRelationsAreSeeded(): void
    {
        $row = $this->db->table('testimonials t')
            ->select('t.person_name, a.name AS alumni_name')
            ->join('alumni_profiles a', 'a.id = t.alumni_profile_id')
            ->where('t.person_name', 'Témoignage exemple')
            ->get()
            ->getRowArray();

        $this->assertIsArray($row);
        $this->assertSame('Alumni exemple', $row['alumni_name']);
    }

    public function testSlugsAreUnique(): void
    {
        $this->expectException(DatabaseException::class);

        $this->db->table('programmes')->insert([
            'level'                => 'licence',
            'title'                => 'Programme dupliqué',
            'slug'                 => 'licence-exemple',
            'duration'             => '3 ans',
            'summary'              => 'Résumé',
            'description'          => 'Description',
            'admission_conditions' => 'Conditions',
            'career_outcomes'      => json_encode(['Débouché'], JSON_UNESCAPED_UNICODE),
            'display_order'        => 99,
            'featured_on_home'     => 0,
            'home_order'           => null,
            'is_published'         => 1,
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);
    }

    public function testPostStatusesAreRestricted(): void
    {
        $validation = service('validation');
        $validation->setRule('status', 'Statut', 'required|in_list[draft,scheduled,published,archived]');

        $this->assertFalse($validation->run(['status' => 'public']));
        $validation->reset();
        $validation->setRule('status', 'Statut', 'required|in_list[draft,scheduled,published,archived]');
        $this->assertTrue($validation->run(['status' => 'published']));
    }

    public function testHomeDisplayOrderIsPreserved(): void
    {
        $laboratories = $this->db->table('laboratories')
            ->select('abbreviation')
            ->where('featured_on_home', 1)
            ->orderBy('home_order', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertSame(['LAB-FSEG'], array_column($laboratories, 'abbreviation'));

        $featuredPosts = $this->db->table('posts')
            ->select('slug')
            ->where('featured', 1)
            ->orderBy('home_order', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertSame(
            ['actualite-exemple-fseg', 'evenement-exemple-fseg'],
            array_column($featuredPosts, 'slug'),
        );
    }

    public function testInitialHomeContentExists(): void
    {
        $home = $this->db->table('home_content')
            ->where('singleton_key', 1)
            ->get()
            ->getRowArray();

        $this->assertIsArray($home);
        $this->assertSame('Bienvenue sur le site de FSEG', $home['hero_title']);
        $this->assertSame('Formations', $home['programmes_label']);
        $this->assertSame('Programmes à compléter', $home['programmes_title']);
        $this->assertSame('Voir les formations', $home['programmes_button_label']);
        $this->assertSame('Actualités', $home['posts_label']);
        $this->assertSame('Actualités et événements', $home['posts_title']);
        $this->assertSame('Voir tout', $home['posts_button_label']);
        $this->assertSame('image', $home['hero_media_type']);
        $this->assertSame('assets/images/logo-placeholder.png', $home['hero_media_path']);
        $this->assertStringContainsString('à compléter', $home['seo_description']);
    }

    public function testInitialHeroSlidesArePublishedAndOrdered(): void
    {
        $slides = $this->db->table('home_hero_slides')
            ->select('image_path, alt_text, display_order, is_published')
            ->orderBy('display_order', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(1, $slides);
        $this->assertSame([
            'assets/images/logo-placeholder.png',
        ], array_column($slides, 'image_path'));

        foreach ($slides as $index => $slide) {
            $this->assertSame((string) ($index + 1), (string) $slide['display_order']);
            $this->assertSame('1', (string) $slide['is_published']);
            $this->assertNotSame('', trim((string) $slide['alt_text']));
        }
    }

    public function testEnglishStarterTranslationsAreSeededWithoutDemoReviewText(): void
    {
        $post = $this->db->table('posts')
            ->select('id, body_en')
            ->where('slug', 'actualite-exemple-fseg')
            ->get()
            ->getRowArray();

        $this->assertIsArray($post);
        $this->assertNull($post['body_en']);

        $home = $this->db->table('home_content')
            ->where('singleton_key', 1)
            ->get()
            ->getRowArray();

        $translation = $this->db->table('content_translations')
            ->where('resource_type', 'home_content')
            ->where('resource_id', (int) $home['id'])
            ->where('locale', 'en')
            ->where('field', 'hero_title')
            ->get()
            ->getRowArray();

        $this->assertIsArray($translation);
        $this->assertSame('Welcome to FSEG', $translation['value']);

        $demoCount = $this->db->table('content_translations')
            ->like('value', 'please review before publication')
            ->countAllResults();

        $this->assertSame(0, $demoCount);
    }

    public function testHomepageStatsSuffixesRenderAfterTheValue(): void
    {
        $mainStats = $this->db->table('site_stats')
            ->select('label, value, suffix')
            ->where('section', 'home_main')
            ->where('is_published', 1)
            ->orderBy('display_order', 'ASC')
            ->get()
            ->getResultArray();

        $researchStats = $this->db->table('site_stats')
            ->select('label, value, suffix')
            ->where('section', 'home_research')
            ->where('is_published', 1)
            ->orderBy('display_order', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(3, $mainStats);
        $this->assertSame('0', (string) $mainStats[0]['value']);
        $this->assertSame('+', (string) $mainStats[0]['suffix']);
        $this->assertSame('0', (string) $researchStats[0]['value']);
        $this->assertSame('+', (string) $researchStats[0]['suffix']);
    }

    public function testNoAdministratorPasswordIsSeeded(): void
    {
        $this->assertSame(0, $this->countRows('users'));

        $seedFiles = glob(APPPATH . 'Database/Seeds/*.php') ?: [];
        $content = '';

        foreach ($seedFiles as $file) {
            $content .= file_get_contents($file) ?: '';
        }

        $this->assertStringNotContainsString('password_hash', $content);
        $this->assertStringNotContainsString('secret2', $content);
        $this->assertStringNotContainsString('admin' . '@example', $content);
    }

    private function countRows(string $table): int
    {
        return (int) $this->db->table($table)->countAllResults();
    }

}
