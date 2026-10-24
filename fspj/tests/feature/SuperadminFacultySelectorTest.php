<?php

use App\Database\Seeds\TemplateStarterSeeder;
use App\Models\SiteModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Validation de la sélecteur de facultés du superadmin (admin-site-switcher).
 *
 * Scénario sous test : un superadministrateur connecté sur une instance
 * facultaire (où le sélecteur s'affiche) choisit une faculté dans la liste.
 * Cela doit (1) déclencher l'événement de sélection (POST admin/site-selection),
 * (2) capturer exactement l'identifiant de la faculté visée, et (3) amorcer et
 * achever le chargement des données de cette faculté (contexte d'administration
 * + listes de ressources scopées sur le site sélectionné).
 *
 * @internal
 */
final class SuperadminFacultySelectorTest extends CIUnitTestCase
{
    use AuthenticationTesting;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    private int $defaultSiteId;
    private int $secondSiteId;

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

        /** @var SiteModel $sites */
        $sites                 = model(SiteModel::class, false);
        $default               = $sites->where('status', 'active')->orderBy('id', 'ASC')->first();
        $this->defaultSiteId   = (int) ($default?->id ?? 1);
        $this->secondSiteId    = $this->createSecondSite();
    }

    // ---------------------------------------------------------------------
    // 1. Le sélecteur déclenche l'événement de sélection (rendu + cible)
    // ---------------------------------------------------------------------

    public function testSelectorRendersForSuperadminWithMultipleFaculties(): void
    {
        $this->actingAs($this->superAdminUser());

        $result = $this->get('/admin');
        $result->assertOK();

        // Le formulaire de sélection est bien présent et cible la route dédiée.
        $result->assertSee('admin-site-switcher');
        $result->assertSee('data-admin-site-switcher');
        $result->assertSee('adminSiteLoading');
        $result->assertSee('Chargement de la faculté');
        $result->assertSee('action="' . site_url('admin/site-selection') . '"');
        // CSP script-src-attr is 'none': inline onchange would never run in the browser.
        $this->assertStringNotContainsString('onchange=', $result->getBody());
        // Les deux facultés sont proposées comme options (valeur = id du site).
        $result->assertSee('value="' . $this->defaultSiteId . '"');
        $result->assertSee('value="' . $this->secondSiteId . '"');
    }

    public function testSelectorDoesNotRenderWhenOnlyOneFacultyExists(): void
    {
        // Supprime la seconde faculté : il ne reste qu'un seul site actif.
        model(SiteModel::class, false)->delete($this->secondSiteId);

        $this->actingAs($this->superAdminUser());

        $result = $this->get('/admin');
        $result->assertOK();
        $result->assertDontSee('admin-site-switcher');
    }

    public function testSelectingFacultyTriggersSelectionEventAndRedirects(): void
    {
        $this->actingAs($this->superAdminUser());

        $result = $this->post('/admin/site-selection', $this->withCsrf([
            'site_id' => (string) $this->secondSiteId,
        ]));

        $result->assertRedirect();
        $result->assertSessionHas('message');
    }

    // ---------------------------------------------------------------------
    // 2. Capture exacte de l'identifiant de la faculté visée
    // ---------------------------------------------------------------------

    public function testSelectedFacultyIdIsCapturedAccurately(): void
    {
        $this->actingAs($this->superAdminUser());

        $this->post('/admin/site-selection', $this->withCsrf([
            'site_id' => (string) $this->secondSiteId,
        ]));

        // La session porte bien l'id sélectionné, ni plus ni moins.
        $this->assertSame((string) $this->secondSiteId, (string) (session('active_admin_site_id') ?? ''));

        // Le résolveur de site restitue le même id une fois remis à zéro.
        service('siteResolver')->reset();
        $this->assertSame($this->secondSiteId, service('siteResolver')->activeSiteId());
        $this->assertNotSame($this->defaultSiteId, service('siteResolver')->activeSiteId());
    }

    public function testSelectingDefaultFacultyCapturesThatIdAndNotTheOther(): void
    {
        $this->actingAs($this->superAdminUser());

        $this->post('/admin/site-selection', $this->withCsrf([
            'site_id' => (string) $this->defaultSiteId,
        ]));

        $this->assertSame((string) $this->defaultSiteId, (string) (session('active_admin_site_id') ?? ''));
        $this->assertNotSame((string) $this->secondSiteId, (string) (session('active_admin_site_id') ?? ''));
    }

    // ---------------------------------------------------------------------
    // 3. Amorçage et achèvement du chargement des données de la faculté choisie
    // ---------------------------------------------------------------------

    public function testSelectingFacultyLoadsAssociatedFacultyRecords(): void
    {
        $this->insertProgramme($this->defaultSiteId, 'Programme FSEG sélection', 'prog-fseg-selection');
        $this->insertProgramme($this->secondSiteId, 'Programme Droit sélection', 'prog-droit-selection');

        $this->actingAs($this->superAdminUser());

        // Avant sélection : le site actif est le site par défaut.
        service('siteResolver')->reset();
        $this->assertSame($this->defaultSiteId, service('siteResolver')->activeSiteId());

        $this->post('/admin/site-selection', $this->withCsrf([
            'site_id' => (string) $this->secondSiteId,
        ]));

        // La sélection persiste en session pour la requête de chargement suivante.
        service('siteResolver')->reset();
        $this->withSession(array_replace($_SESSION, ['active_admin_site_id' => $this->secondSiteId]));

        // Après sélection : la liste des ressources est scopée sur la faculté choisie.
        $list = $this->get('/admin/programmes');
        $list->assertOK();
        $list->assertSee('Programme Droit sélection');
        $list->assertDontSee('Programme FSEG sélection');
    }

    public function testDashboardReflectsSelectedFacultyAfterSelection(): void
    {
        $this->actingAs($this->superAdminUser());

        $this->post('/admin/site-selection', $this->withCsrf([
            'site_id' => (string) $this->secondSiteId,
        ]));

        service('siteResolver')->reset();
        $this->withSession(array_replace($_SESSION, ['active_admin_site_id' => $this->secondSiteId]));

        // Le tableau de bord affiche le site désormais actif dans la barre supérieure.
        $dashboard = $this->get('/admin');
        $dashboard->assertOK();

        $site = model(SiteModel::class, false)->find($this->secondSiteId);
        $dashboard->assertSee($site?->name ?? '');
    }

    public function testCentralSwitcherLandsOnFacultyWorkspaceAndScopesPosts(): void
    {
        $this->insertPost($this->defaultSiteId, 'Actualité FSEG isolée', 'actu-fseg-isolee');
        $this->insertPost($this->secondSiteId, 'Actualité Droit isolée', 'actu-droit-isolee');

        $this->actingAs($this->superAdminUser());

        $select = $this->withHeaders(['Host' => 'admin.ub.local'])->post('/admin/site-selection', $this->withCsrf([
            'site_id' => (string) $this->secondSiteId,
        ]));
        $select->assertRedirect();
        $this->assertStringContainsString('/admin/site', (string) $select->response()->getHeaderLine('Location'));

        service('siteResolver')->reset();
        $this->withSession(array_replace($_SESSION, ['active_admin_site_id' => $this->secondSiteId]));

        $workspace = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin/site');
        $workspace->assertOK();
        $workspace->assertSee('Vous modifiez : Faculté de Droit');
        $workspace->assertSee('Accueil');
        $workspace->assertDontSee('Pages institutionnelles');
        $workspace->assertDontSee('Blocs de page');

        $posts = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin/posts');
        $posts->assertOK();
        $posts->assertSee('Actualité Droit isolée');
        $posts->assertDontSee('Actualité FSEG isolée');
    }

    public function testCentralForbidsNewSiteScreenAndHidesTemplateSlug(): void
    {
        $this->db->table('sites')->insert([
            'identifier'     => 'template',
            'name'           => 'Squelette interne TEMPLATEUNIQUE',
            'slug'           => 'template',
            'hostnames'      => json_encode(['template.test']),
            'status'         => 'active',
            'default_locale' => 'fr',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->actingAs($this->superAdminUser());

        $newSite = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin/sites/new');
        $newSite->assertRedirect();
        $newSite->assertSessionHas('error');

        $dashboard = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin');
        $dashboard->assertOK();
        $dashboard->assertDontSee('TEMPLATEUNIQUE');
        $dashboard->assertDontSee('Créer une faculté');
    }

    // ---------------------------------------------------------------------
    // 4. Garde-fous : la sélection ne doit pas être déclenchée hors périmètre
    // ---------------------------------------------------------------------

    public function testNonSuperadminCannotTriggerSelection(): void
    {
        $editor = $this->editorUser();
        $this->actingAs($editor);

        $result = $this->post('/admin/site-selection', $this->withCsrf([
            'site_id' => (string) $this->secondSiteId,
        ]));

        $result->assertRedirect();
        $result->assertSessionHas('error');
        $this->assertNotSame((string) $this->secondSiteId, (string) (session('active_admin_site_id') ?? ''));
    }

    public function testSelectingNonExistentSiteIsRejected(): void
    {
        $this->actingAs($this->superAdminUser());

        $result = $this->post('/admin/site-selection', $this->withCsrf([
            'site_id' => '999999',
        ]));

        $result->assertRedirect();
        $result->assertSessionHas('error');
        $this->assertNotSame('999999', (string) (session('active_admin_site_id') ?? ''));
    }

    public function testSelectionWithoutCsrfTokenIsRejected(): void
    {
        $this->actingAs($this->superAdminUser());

        try {
            $this->post('/admin/site-selection', [
                'site_id' => (string) $this->secondSiteId,
            ]);
            $this->fail('Une requête sans jeton CSRF doit être refusée.');
        } catch (CodeIgniter\Security\Exceptions\SecurityException) {
        }

        $this->assertNotSame((string) $this->secondSiteId, (string) (session('active_admin_site_id') ?? ''));
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function createSecondSite(): int
    {
        $existing = $this->db->table('sites')->where('slug', 'droit')->get()->getRowArray();
        if (is_array($existing)) {
            return (int) $existing['id'];
        }

        $id = model(SiteModel::class, false)->insert([
            'identifier'      => 'droit',
            'name'            => 'Faculté de Droit',
            'slug'            => 'droit',
            'hostnames'       => ['droit.test'],
            'status'          => 'active',
            'default_locale'  => 'fr',
            'logo'            => 'assets/images/logo-placeholder.png',
            'primary_color'   => '#0D9B49',
            'secondary_color' => '#0B6F38',
            'contact_email'   => 'droit@example.test',
            'phone'           => '+257 22 22 00 00',
            'address'         => 'Bujumbura',
        ], true);

        $this->assertNotFalse($id);

        return (int) $id;
    }

    private function insertPost(int $siteId, string $title, string $slug): void
    {
        $this->db->table('posts')->insert([
            'site_id'      => $siteId,
            'type'         => 'news',
            'title'        => $title,
            'slug'         => $slug,
            'excerpt'      => 'Extrait de test.',
            'body'         => 'Corps de test.',
            'status'       => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'featured'     => 0,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertProgramme(int $siteId, string $title, string $slug): void
    {
        $this->db->table('programmes')->insert([
            'site_id'              => $siteId,
            'level'                => 'licence',
            'title'                => $title,
            'slug'                 => $slug,
            'duration'             => '3 ans',
            'summary'              => 'Résumé de test.',
            'description'          => 'Description de test.',
            'admission_conditions' => 'Diplôme requis.',
            'career_outcomes'      => json_encode(['Débouché'], JSON_UNESCAPED_UNICODE),
            'display_order'        => 1,
            'featured_on_home'     => 0,
            'home_order'           => null,
            'is_published'         => 1,
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);
    }

    private function superAdminUser(): User
    {
        return $this->userWithGroup('selector-super@example.test', 'selectorsuper', 'superadmin');
    }

    private function editorUser(): User
    {
        return $this->userWithGroup('selector-editor@example.test', 'selectoreditor', 'editor');
    }

    private function userWithGroup(string $email, string $username, string $group): User
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);
        $existing = $users->where('username', $username)->first();

        if ($existing instanceof User) {
            return $existing;
        }

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => 'MotDePasseSelecteur!2026',
            'active'   => 1,
        ]);

        $users->save($user);

        /** @var User $created */
        $created = $users->findById($users->getInsertID());
        $created->addGroup($group);
        $created->activate();

        return $created;
    }

    public function testCentralWithoutSelectionBlocksContentEditing(): void
    {
        $this->actingAs($this->superAdminUser());
        $_SESSION = [];
        $this->withSession([]);
        service('siteResolver')->reset();

        $result = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin/programmes');
        $result->assertRedirect();
        $result->assertSessionHas('error');
    }

    public function testSiteSelectionHonorsReturnTo(): void
    {
        $this->actingAs($this->superAdminUser());
        $returnTo = site_url('admin/users');

        $result = $this->post('/admin/site-selection', $this->withCsrf([
            'site_id'   => (string) $this->secondSiteId,
            'return_to' => $returnTo,
        ]));

        $result->assertRedirect();
        $this->assertStringContainsString('/admin/users', (string) $result->response()->getHeaderLine('Location'));
    }

    public function testSiteSelectionFromEditUrlLandsOnModuleIndex(): void
    {
        $this->actingAs($this->superAdminUser());
        $returnTo = site_url('admin/programmes/12/edit');

        $result = $this->post('/admin/site-selection', $this->withCsrf([
            'site_id'   => (string) $this->secondSiteId,
            'return_to' => $returnTo,
        ]));

        $result->assertRedirect();
        $location = (string) $result->response()->getHeaderLine('Location');
        $this->assertSame(site_url('admin/programmes'), $location);
    }

    public function testSiteSelectionHtmxSetsRedirectHeader(): void
    {
        $this->actingAs($this->superAdminUser());

        $result = $this->withHeaders([
            'HX-Request' => 'true',
        ])->post('/admin/site-selection', $this->withCsrf([
            'site_id'   => (string) $this->secondSiteId,
            'return_to' => site_url('admin/home-hero-slides/3/edit'),
        ]));

        $this->assertSame(204, $result->response()->getStatusCode());
        $location = (string) $result->response()->getHeaderLine('HX-Redirect');
        $this->assertStringContainsString('/admin/home-hero-slides', $location);
        $this->assertStringNotContainsString('/edit', $location);
    }

    public function testRetiredPagesModuleRedirectsAway(): void
    {
        $this->actingAs($this->superAdminUser());
        $this->withSession(['active_admin_site_id' => $this->defaultSiteId]);
        service('siteResolver')->reset();

        $result = $this->get('/admin/pages');
        $result->assertRedirect();
        $result->assertSessionHas('error');
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
