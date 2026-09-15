<?php

use App\Controllers\Admin\HomeSectionsController;
use App\Database\Seeds\TemplateStarterSeeder;
use App\Models\SiteModel;
use App\Services\HomePageService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Couverture ciblée du chantier Admin UX (nav, héros par slide, sections, icônes).
 *
 * @internal
 */
final class AdminUxOverhaulTest extends CIUnitTestCase
{
    use AuthenticationTesting;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    protected function setUp(): void
    {
        parent::setUp();

        service('siteResolver')->reset();
        service('settingsService')->reset();
        service('contentTranslationService')->reset();

        try {
            auth('session')->getAuthenticator()->logout();
        } catch (Throwable) {
        }

        $_SESSION = [];
        $this->withSession([]);
    }

    public function testNavExposesNewZonesAndHidesRetiredModules(): void
    {
        $this->actingAs($this->adminUser());

        $result = $this->get('/admin');
        $result->assertOK();
        $result->assertSee('Accueil');
        $result->assertSee('Pages du site');
        $result->assertSee('Héros (slides)');
        $result->assertSee('Sections &amp; ordre');
        $result->assertDontSee('Pages institutionnelles');
        $result->assertDontSee('Blocs de page');
    }

    public function testHeroSlideAcceptsPerSlideCopyAndCtaTargets(): void
    {
        $this->actingAs($this->adminUser());
        $siteId = service('siteResolver')->activeSiteId();

        $this->db->table('home_hero_slides')->insert([
            'site_id'              => $siteId,
            'image_path'           => 'assets/images/hero/campus-walkway.jpg',
            'alt_text'             => 'Campus test',
            'badge'                => null,
            'title'                => null,
            'text'                 => null,
            'primary_cta_target'   => 'none',
            'secondary_cta_target' => 'none',
            'display_order'        => 3,
            'is_published'         => 1,
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);
        $slideId = (int) $this->db->insertID();

        $form = $this->get('/admin/home-hero-slides/' . $slideId . '/edit');
        $form->assertOK();
        $form->assertSee('Textes du slide');
        $form->assertSee('name="primary_cta_target"');
        $form->assertSee('name="badge"');

        $update = $this->post('/admin/home-hero-slides/' . $slideId, $this->withCsrf([
            'alt_text'             => 'Campus test',
            'badge'                => 'Badge slide',
            'title'                => 'Titre slide UX',
            'text'                 => 'Texte du slide pour le carrousel.',
            'primary_cta_target'   => 'programmes',
            'primary_cta_label'    => 'Formations',
            'secondary_cta_target' => 'contact',
            'secondary_cta_label'  => 'Contact',
            'display_order'        => '3',
            'is_published'         => '1',
        ]));
        $update->assertRedirect();

        $slide = $this->db->table('home_hero_slides')->where('id', $slideId)->get()->getRowArray();
        $this->assertIsArray($slide);
        $this->assertSame('Badge slide', $slide['badge']);
        $this->assertSame('Titre slide UX', $slide['title']);
        $this->assertSame('programmes', $slide['primary_cta_target']);
        $this->assertSame('contact', $slide['secondary_cta_target']);

        $service = new HomePageService();
        $this->assertStringContainsString('formations', (string) $service->resolveCtaUrl('programmes'));
        $this->assertStringContainsString('contact', (string) $service->resolveCtaUrl('contact'));
        $this->assertNull($service->resolveCtaUrl('none'));
        $this->assertStringContainsString('example.test/x', (string) $service->resolveCtaUrl('custom', 'https://example.test/x'));
    }

    public function testHomeSectionsOrderPersistsAndPublicHomeUsesCssOrder(): void
    {
        $this->actingAs($this->adminUser());

        $order = [
            'contact_cta',
            'hero',
            'about',
            'news_preview',
        ];

        $save = $this->post('/admin/home-sections', $this->withCsrf([
            'order'    => json_encode($order),
            'sections' => $order,
        ]));
        $save->assertRedirect();

        $siteId  = service('siteResolver')->activeSiteId();
        $site    = model(SiteModel::class, false)->find($siteId);
        $enabled = $site->enabled_sections ?? [];
        if (is_string($enabled)) {
            $enabled = json_decode($enabled, true) ?: [];
        }
        $this->assertSame($order, array_values($enabled));

        $home = $this->get('/');
        $home->assertOK();
        $body = $home->response()->getBody();
        // Contact is first in enabled_sections → CSS order 0; hero is second → order 1.
        // Markup puts class before style (and hero may append CSS variables).
        $this->assertMatchesRegularExpression(
            '/<section[^>]*style="order:\s*0[;"][^>]*>[\s\S]{0,500}section-label">Contact/i',
            $body,
        );
        $this->assertMatchesRegularExpression(
            '/<section[^>]*class="hero"[^>]*style="order:\s*1[;"]/i',
            $body,
        );
    }

    public function testUsersIndexUsesIconActionsAndFacultyFilter(): void
    {
        $this->actingAs($this->superAdminUser());

        $result = $this->get('/admin/users?site_id=');
        $result->assertOK();
        $result->assertSee('admin-row-actions');
        $result->assertSee('Faculté');
        $result->assertSee('Toutes');
        $result->assertSee('Créer un compte');
        $result->assertSee('bi-pencil');
    }

    public function testAvailableHomeSectionsMatchProvisioningDefaults(): void
    {
        $available = HomeSectionsController::availableSections();
        $this->assertContains('hero', $available);
        $this->assertContains('dean_message', $available);
        $this->assertContains('contact_cta', $available);
        $this->assertNotContains('pages', $available);
    }

    private function adminUser(): User
    {
        return $this->userWithGroup('ux-admin@example.test', 'uxadmin', 'admin', 'site_admin');
    }

    private function superAdminUser(): User
    {
        return $this->userWithGroup('ux-super@example.test', 'uxsuper', 'superadmin');
    }

    private function userWithGroup(string $email, string $username, string $group, ?string $siteRole = null): User
    {
        /** @var UserModel $users */
        $users    = model(UserModel::class);
        $existing = $users->where('username', $username)->first();

        if ($existing instanceof User) {
            if ($siteRole !== null) {
                service('siteResolver')->syncUserSites((int) $existing->id, [1], $siteRole);
            }

            return $existing;
        }

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => 'MotDePasseUx!2026',
            'active'   => 1,
        ]);

        $users->save($user);

        /** @var User $created */
        $created = $users->findById($users->getInsertID());
        $created->removeGroup('editor', 'admin', 'superadmin');
        $created->addGroup($group);
        $created->activate();

        if ($siteRole !== null) {
            service('siteResolver')->syncUserSites((int) $created->id, [1], $siteRole);
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function withCsrf(array $data = []): array
    {
        $security = service('security');
        $token    = $security->getTokenName();
        $hash     = $security->generateHash();

        $this->withSession(array_replace($_SESSION, [$token => $hash]));

        return array_replace([$token => $hash], $data);
    }
}
