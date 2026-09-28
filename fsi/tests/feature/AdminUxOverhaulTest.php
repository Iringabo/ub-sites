<?php

use App\Controllers\Admin\HomeSectionsController;
use App\Database\Seeds\TemplateStarterSeeder;
use App\Models\SiteModel;
use App\Services\FacultyDemoDataService;
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
        $body = (string) $result->response()->getBody();

        $headings = [];
        preg_match_all('/<h2 class="admin-nav-heading"[^>]*>([^<]+)<\/h2>/', $body, $headings);
        $this->assertSame(['Au quotidien', 'Pages du site', 'Paramètres du site', 'Administration'], $headings[1]);

        foreach (['Accueil', 'La Faculté', 'Formations', 'Recherche', 'Corps enseignant', 'Alumni', 'Contact'] as $page) {
            $result->assertSee($page);
        }
        $result->assertSee('Sections de la page');
        $result->assertSee('Listes');
        $result->assertSee('Carrousel d’images');
        $result->assertSee('Ordre des blocs');
        $result->assertSee('Chiffres clés');
        $result->assertSee('Mot du doyen');
        $this->assertStringContainsString('Identité &amp; logo', $body);
        $result->assertSee('Messages reçus');
        $this->assertStringContainsString('href="' . site_url('admin/textes/faculte/mot-du-doyen') . '"', $body);
        $this->assertStringContainsString('data-keywords="', $body);
        $this->assertStringContainsString('admin-nav-page-view', $body);
        $result->assertDontSee('Héros (slides)');
        $result->assertDontSee('Textes des sections');
        $result->assertDontSee('Pages institutionnelles');
        $result->assertDontSee('Blocs de page');
    }

    public function testNavOpensThePageDropdownOfTheCurrentCategory(): void
    {
        $this->actingAs($this->adminUser());

        $body = (string) $this->get('/admin/textes/recherche/bandeau')->response()->getBody();

        $this->assertMatchesRegularExpression('/id="adminNavPageRecherche" class="collapse show"/', $body);
        $this->assertMatchesRegularExpression('/id="adminNavPageFaculte" class="collapse "/', $body);
        $this->assertMatchesRegularExpression('/class="admin-nav-link active"[^>]+href="[^"]*admin\/textes\/recherche\/bandeau"/', $body);
    }

    public function testUnreadMessagesShowAsBadgeAndDashboardQuickAction(): void
    {
        $this->actingAs($this->adminUser());
        $siteId = service('siteResolver')->activeSiteId();
        $this->db->table('contact_messages')->where('site_id', $siteId)->update(['status' => 'read']);
        $this->db->table('contact_messages')->insert([
            'site_id'    => $siteId,
            'name'       => 'Visiteur badge',
            'email'      => 'badge@example.test',
            'subject'    => 'Question',
            'message'    => 'Message non lu pour le badge.',
            'status'     => 'new',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->get('/admin');
        $result->assertOK();
        $body = (string) $result->response()->getBody();
        $this->assertMatchesRegularExpression('/<span class="admin-nav-badge"[^>]*>\s*1\s*<span class="visually-hidden">/', $body);
        $result->assertSee('Publier une actualité');
        $result->assertSee('Ajouter un événement');
        $result->assertSee('Voir les messages non lus');
    }

    public function testHeroSlideAcceptsPerSlideCopyAndCtaTargets(): void
    {
        $this->actingAs($this->adminUser());
        $siteId = service('siteResolver')->activeSiteId();

        $this->db->table('home_hero_slides')->insert([
            'site_id'              => $siteId,
            'image_path'           => 'assets/images/logo-placeholder.png',
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
        $form->assertSee('Modifiez-le en glissant les lignes');
        $formBody = (string) $form->response()->getBody();
        $this->assertDoesNotMatchRegularExpression('/<input[^>]+type="number"[^>]+name="display_order"/', $formBody);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]+name="display_order"[^>]+type="number"/', $formBody);

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
            'quick_links',
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
        // Quick links follow the saved order (third → order 2) and link to the four key sections.
        $this->assertMatchesRegularExpression(
            '/<section[^>]*class="quick-links"[^>]*style="order:\s*2"/i',
            $body,
        );
        $decoded = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('Accès rapides', $decoded);
        foreach (['formations', 'recherche', 'actualites', 'contact'] as $path) {
            $this->assertMatchesRegularExpression('#href="[^"]*/' . $path . '" class="quick-link h-100"#', $decoded);
        }
        $this->assertSame(4, preg_match_all('/class="quick-link h-100"/', $body));
    }

    public function testQuickLinksAreHiddenWhenNotEnabledForTheSite(): void
    {
        $this->actingAs($this->adminUser());

        $save = $this->post('/admin/home-sections', $this->withCsrf([
            'order'    => json_encode(['hero', 'about']),
            'sections' => ['hero', 'about'],
        ]));
        $save->assertRedirect();

        $home = $this->get('/');
        $home->assertOK();
        $this->assertStringNotContainsString('class="quick-links"', (string) $home->response()->getBody());
    }

    public function testHeroIndicatorSizeCanBeSavedFromSlidesIndex(): void
    {
        $this->actingAs($this->adminUser());

        $index = $this->get('/admin/home-hero-slides');
        $index->assertOK();
        $index->assertSee('Taille des pastilles du carrousel');
        $index->assertSee('name="indicator_size"');

        $save = $this->post('/admin/home-hero-slides/indicator-size', $this->withCsrf([
            'indicator_size' => '1.25',
        ]));
        $save->assertRedirect();

        $siteId = service('siteResolver')->activeSiteId();
        $row = $this->db->table('settings')
            ->where('site_id', $siteId)
            ->where('key', 'home.hero_indicator_size')
            ->get()
            ->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame('1.25', $row['value']);

        service('settingsService')->reset();
        $home = $this->get('/');
        $home->assertOK();
        $this->assertStringContainsString('--hero-indicator-size: 1.25rem', $home->response()->getBody());
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
        $this->assertContains('quick_links', $available);
        $this->assertContains('dean_message', $available);
        $this->assertContains('contact_cta', $available);
        $this->assertNotContains('pages', $available);
        $this->assertNotContains('highlights', $available);
        $this->assertArrayHasKey('quick_links', HomeSectionsController::sectionLabels());

        // Provisioning and demo seed share the same visitor-first default order,
        // and every default section is one the admin can manage.
        $defaults = FacultyDemoDataService::DEFAULT_SECTION_ORDER;
        $this->assertSame(['hero', 'quick_links', 'statistics'], array_slice($defaults, 0, 3));
        $this->assertSame('contact_cta', end($defaults));
        $this->assertSame([], array_diff($defaults, $available));
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
