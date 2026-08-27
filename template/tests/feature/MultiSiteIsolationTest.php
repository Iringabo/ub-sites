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
 * @internal
 */
final class MultiSiteIsolationTest extends CIUnitTestCase
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

    public function testPublicSiteIsResolvedFromAllowListedHostname(): void
    {
        $siteId = $this->createSecondSite();
        $this->copyHomeContentForSite($siteId, 'Accueil Faculté de Droit');

        service('siteResolver')->reset();
        service('settingsService')->reset();

        $result = $this
            ->withHeaders(['Host' => 'droit.test'])
            ->get('/');

        $result->assertOK();
        $result->assertSee('Accueil Faculté de Droit');
        $result->assertDontSee('Bienvenue sur le site de FSEG');
    }

    public function testStrictPublicHostnameRejectsUnlistedHostname(): void
    {
        $this->setEnvironment('app.requireKnownHostname', 'true');
        $siteModel = model(SiteModel::class, false);
        $defaultSite = $siteModel->find(1);
        $originalHostnames = $defaultSite?->hostnames ?? [];

        try {
            $siteModel->update(1, [
                'hostnames' => ['fseg.test'],
            ]);

            service('siteResolver')->reset();
            try {
                $this
                    ->withHeaders(['Host' => '127.0.0.1:8080'])
                    ->get('/');

                $this->fail('Un hôte public inconnu doit être rejeté en mode strict.');
            } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
                $this->addToAssertionCount(1);
            }

            service('siteResolver')->reset();
            $knownHost = $this
                ->withHeaders(['Host' => 'fseg.test:8080'])
                ->get('/');

            $knownHost->assertOK();
        } finally {
            $siteModel->update(1, [
                'hostnames' => $originalHostnames,
            ]);
            $this->clearEnvironment('app.requireKnownHostname');
            service('siteResolver')->reset();
        }
    }

    public function testSameSlugCanExistOnTwoSitesAndPublicDetailStaysIsolated(): void
    {
        $siteId = $this->createSecondSite();
        $existing = $this->db->table('programmes')
            ->where('site_id', 1)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();

        $this->assertIsArray($existing);

        $this->db->table('programmes')->insert([
            'site_id'              => $siteId,
            'level'                => 'licence',
            'title'                => 'Licence en droit économique',
            'slug'                 => $existing['slug'],
            'duration'             => '3 ans',
            'summary'              => 'Résumé propre au site de droit.',
            'description'          => 'Description propre au site de droit.',
            'admission_conditions' => 'Diplôme requis.',
            'career_outcomes'      => json_encode(['Juriste économique'], JSON_UNESCAPED_UNICODE),
            'display_order'        => 1,
            'featured_on_home'     => 1,
            'home_order'           => 1,
            'is_published'         => 1,
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        service('siteResolver')->reset();

        $result = $this
            ->withHeaders(['Host' => 'droit.test'])
            ->get('/formations/' . $existing['slug']);

        $result->assertOK();
        $result->assertSee('Licence en droit économique');
        $result->assertDontSee($existing['title']);
    }

    public function testAdministrationListsOnlySelectedSiteContent(): void
    {
        $siteId = $this->createSecondSite();
        $this->insertProgramme(1, 'Programme FSEG isolé', 'programme-fseg-isole');
        $this->insertProgramme($siteId, 'Programme Droit isolé', 'programme-droit-isole');

        $this->actingAs($this->superAdminUser());

        service('siteResolver')->reset();
        $default = $this->get('/admin/programmes');
        $default->assertOK();
        $default->assertSee('Programme FSEG isolé');
        $default->assertDontSee('Programme Droit isolé');

        $this->post('/admin/site-selection', $this->withCsrf(['site_id' => (string) $siteId]))
            ->assertRedirectTo(site_url('admin'));

        service('siteResolver')->selectAdminSite($siteId, auth()->user());
        service('settingsService')->reset();
        $this->withSession(array_replace($_SESSION, ['active_admin_site_id' => $siteId]));

        $switched = $this->get('/admin/programmes');
        $switched->assertOK();
        $switched->assertSee('Programme Droit isolé');
        $switched->assertDontSee('Programme FSEG isolé');
    }

    public function testFacultyAdministratorCannotSelectUnassignedSite(): void
    {
        $siteId = $this->createSecondSite();
        $admin = $this->adminUser();
        service('siteResolver')->syncUserSites((int) $admin->id, [1]);
        $this->actingAs($admin);

        $result = $this->post('/admin/site-selection', $this->withCsrf(['site_id' => (string) $siteId]));

        $result->assertRedirect();
        $result->assertSessionHas('error');
        $this->assertFalse(service('siteResolver')->canUserAccessSite($siteId, $admin));
    }

    public function testCentralAdminHostRedirectsRootAndAllowsOnlySuperAdmin(): void
    {
        $this
            ->withHeaders(['Host' => 'admin.ub.local'])
            ->get('/')
            ->assertRedirect();

        $facultyAdmin = $this->adminUser();
        service('siteResolver')->syncUserSites((int) $facultyAdmin->id, [1], 'site_admin');
        $this->actingAs($facultyAdmin);

        $this
            ->withHeaders(['Host' => 'admin.ub.local'])
            ->get('/admin')
            ->assertStatus(403);

        $this->actingAs($this->superAdminUser('central-super@example.test', 'centralsuper'));

        $central = $this
            ->withHeaders(['Host' => 'admin.ub.local'])
            ->get('/admin');

        $central->assertOK();
        $central->assertSee('Superadministration');
        $central->assertSee('Facultés');
    }

    public function testFacultyFolderWithUnknownConfiguredSiteFailsInsteadOfServingAnotherFaculty(): void
    {
        // Régression audit multi-dossiers : si app.siteSlug ne correspond à
        // aucun site actif, le dossier doit répondre 404 proprement plutôt
        // que de replacer silencieusement vers le premier site (fuite).
        $this->setEnvironment('app.siteSlug', 'site-inexistant');

        try {
            service('siteResolver')->reset();
            service('settingsService')->reset();

            try {
                $this
                    ->withHeaders(['Host' => '127.0.0.1:8080'])
                    ->get('/');

                $this->fail('Un dossier facultaire lié à un site inexistant doit échouer explicitement.');
            } catch (\CodeIgniter\Exceptions\PageNotFoundException $exception) {
                $this->assertStringContainsString('site facultaire introuvable', $exception->getMessage());
            }
        } finally {
            $this->clearEnvironment('app.siteSlug');
            putenv('app.siteSlug=fseg');
            $_ENV['app.siteSlug']    = 'fseg';
            $_SERVER['app.siteSlug'] = 'fseg';
            service('siteResolver')->reset();
            service('settingsService')->reset();
        }
    }

    public function testCentralAdminModeEnvFlagForcesCentralAdminRegardlessOfHost(): void
    {
        // Simulates the folder-based deployment: this instance's own .env
        // sets app.centralAdminMode = true, independent of any hostname.
        putenv('app.centralAdminMode=true');
        $_ENV['app.centralAdminMode']    = 'true';
        $_SERVER['app.centralAdminMode'] = 'true';

        try {
            service('siteResolver')->reset();

            // A faculty public route must never render on this instance;
            // it always bounces to /admin.
            $this
                ->withHeaders(['Host' => 'fseg.test'])
                ->get('/formations')
                ->assertRedirectTo('/admin');

            $facultyAdmin = $this->adminUser();
            service('siteResolver')->syncUserSites((int) $facultyAdmin->id, [1], 'site_admin');
            $this->actingAs($facultyAdmin);

            $this
                ->withHeaders(['Host' => 'fseg.test'])
                ->get('/admin')
                ->assertStatus(403);

            $this->actingAs($this->superAdminUser('central-flag-super@example.test', 'centralflagsuper'));

            $central = $this
                ->withHeaders(['Host' => 'fseg.test'])
                ->get('/admin');

            $central->assertOK();
            $central->assertSee('Superadministration');
        } finally {
            putenv('app.centralAdminMode');
            unset($_ENV['app.centralAdminMode'], $_SERVER['app.centralAdminMode']);
            service('siteResolver')->reset();
        }
    }

    public function testFacultyAdminHostAllowsAssignedFacultyAndRejectsAnotherFaculty(): void
    {
        $droitSiteId = $this->createSecondSite();
        $droitAdmin = $this->userWithGroup('droit-admin@example.test', 'droitadmin', 'admin');
        service('siteResolver')->syncUserSites((int) $droitAdmin->id, [$droitSiteId], 'site_admin');

        $this->actingAs($droitAdmin);
        $allowed = $this
            ->withHeaders(['Host' => 'droit.test'])
            ->get('/admin');

        $allowed->assertOK();
        $allowed->assertSee('Faculté de Droit');

        $fsegAdmin = $this->userWithGroup('fseg-host-admin@example.test', 'fseghostadmin', 'admin');
        service('siteResolver')->syncUserSites((int) $fsegAdmin->id, [1], 'site_admin');

        $this->actingAs($fsegAdmin);
        $denied = $this
            ->withHeaders(['Host' => 'droit.test'])
            ->get('/admin');

        $denied->assertRedirect();
        $denied->assertSessionHas('error');
    }

    public function testFacultyFolderWithoutMatchingHostnameRejectsStaffOfAnotherFaculty(): void
    {
        // Dossier facultaire servi sous un hôte non listé : le site lié au
        // dossier vient de la configuration (app.siteSlug), jamais d'un
        // repli silencieux vers le site attribué à l'utilisateur courant.
        $siteId = $this->createSecondSite();
        $droitAdmin = $this->userWithGroup('bound-droit-admin@example.test', 'bounddroitadmin', 'admin');
        service('siteResolver')->syncUserSites((int) $droitAdmin->id, [$siteId], 'site_admin');

        $this->actingAs($droitAdmin);

        $denied = $this
            ->withHeaders(['Host' => 'serveur-interne.test'])
            ->get('/admin');

        $denied->assertRedirect();
        $denied->assertSessionHas('error');
        $this->assertFalse(service('siteResolver')->canUserAccessSite(1, $droitAdmin));
    }

    public function testSuperadminKeepsAccessToAnyFacultyFolder(): void
    {
        $this->actingAs($this->superAdminUser('folder-super@example.test', 'foldersuper'));

        $allowed = $this
            ->withHeaders(['Host' => 'serveur-interne.test'])
            ->get('/admin');

        $allowed->assertOK();
    }

    public function testOnlySuperadminsCanUseTheSiteSwitcher(): void
    {
        $editor = $this->userWithGroup('switch-editor@example.test', 'switcheditor', 'editor');
        service('siteResolver')->syncUserSites((int) $editor->id, [1], 'editor');

        $this->actingAs($editor);

        $result = $this->post('/admin/site-selection', $this->withCsrf(['site_id' => '1']));

        $result->assertRedirect();
        $result->assertSessionHas('error');
    }

    public function testFacultyUserManagementIsScopedToActiveFacultyEditors(): void
    {
        $droitSiteId = $this->createSecondSite();
        $droitAdmin = $this->userWithGroup('scoped-droit-admin@example.test', 'scopeddroitadmin', 'admin');
        $droitEditor = $this->userWithGroup('scoped-droit-editor@example.test', 'scopeddroiteditor', 'editor');
        $fsegEditor = $this->userWithGroup('scoped-fseg-editor@example.test', 'scopedfsegeditor', 'editor');

        service('siteResolver')->syncUserSites((int) $droitAdmin->id, [$droitSiteId], 'site_admin');
        service('siteResolver')->syncUserSites((int) $droitEditor->id, [$droitSiteId], 'editor');
        service('siteResolver')->syncUserSites((int) $fsegEditor->id, [1], 'editor');

        $this->actingAs($droitAdmin);

        $index = $this
            ->withHeaders(['Host' => 'droit.test'])
            ->get('/admin/users');

        $index->assertOK();
        $index->assertSee('scopeddroiteditor');
        $index->assertDontSee('scopedfsegeditor');
        $index->assertDontSee('Superadministrateur');

        $create = $this
            ->withHeaders(['Host' => 'droit.test'])
            ->post('/admin/users', $this->withCsrf([
                'username'    => 'droitcreateditor',
                'email'       => 'droit-created-editor@example.test',
                'password'    => 'MotDePasseDroit!2026',
                'confirm'     => 'MotDePasseDroit!2026',
                'active'      => '1',
                'groups'      => ['editor'],
                'permissions' => [],
                'site_ids'    => ['1'],
                'site_roles'  => ['1' => 'site_admin'],
            ]));

        $create->assertRedirect();

        $created = $this->db->table('users')
            ->where('username', 'droitcreateditor')
            ->get()
            ->getRowArray();

        $this->assertIsArray($created);
        $assignment = $this->db->table('user_sites')
            ->where('user_id', (int) $created['id'])
            ->get()
            ->getRowArray();

        $this->assertSame((string) $droitSiteId, (string) $assignment['site_id']);
        $this->assertSame('editor', $assignment['role']);
    }

    public function testNewFacultyCreationProvisionsStarterContentForOnlyThatSite(): void
    {
        $this->actingAs($this->superAdminUser('provision-super@example.test', 'provisionsuper'));

        $response = $this
            ->withHeaders(['Host' => 'admin.ub.local'])
            ->post('/admin/sites', $this->withCsrf([
                'identifier'       => 'fsi_platform',
                'name'             => 'Faculté des Sciences de l’Ingénieur',
                'slug'             => 'fsi-platform',
                'hostnames'        => "fsi-platform.test\nfsi.ub.local",
                'status'           => 'active',
                'default_locale'   => 'fr',
                'primary_color'    => '#14532D',
                'secondary_color'  => '#0F766E',
                'theme'            => 'research',
                'theme_config'     => '{"layout":"research","hero_image":"assets/images/hero/research-team.jpg"}',
                'menu_config'      => '{"items":["faculte","formations","recherche","contact"]}',
                'enabled_sections' => "hero\nprogrammes_preview\nresearch_labs\ncontact_cta",
                'contact_email'    => 'fsi-platform@example.test',
                'phone'            => '+257 22 22 11 11',
                'address'          => 'Campus Kiriri',
            ]));

        $response->assertRedirect();

        $site = $this->db->table('sites')->where('slug', 'fsi-platform')->get()->getRowArray();
        $this->assertIsArray($site);
        $siteId = (int) $site['id'];

        $this->assertSame('research', $site['theme']);
        $this->assertSame(1, $this->db->table('home_content')->where('site_id', $siteId)->countAllResults());
        $this->assertGreaterThanOrEqual(7, $this->db->table('pages')->where('site_id', $siteId)->countAllResults());
        $this->assertGreaterThanOrEqual(5, $this->db->table('content_blocks')->where('site_id', $siteId)->countAllResults());
        // Le provisionnement du nouveau site ne doit rien ajouter au site 1.
        $this->assertSame(5, $this->db->table('content_blocks')->where('site_id', 1)->countAllResults());

        service('siteResolver')->reset();
        service('settingsService')->reset();

        $public = $this
            ->withHeaders(['Host' => 'fsi-platform.test'])
            ->get('/');

        $public->assertOK();
        $public->assertSee('Bienvenue sur le site de FSI_PLATFORM');
        $publicBody = (string) $public->response()->getBody();
        $this->assertStringContainsString('theme-research', $publicBody);
        $this->assertStringContainsString('--green: #14532D', $publicBody);
    }

    public function testThemeAndContentBlocksRemainIsolatedPerFaculty(): void
    {
        $droitSiteId = $this->createSecondSite();

        model(SiteModel::class, false)->update($droitSiteId, [
            'theme'           => 'modern',
            'primary_color'   => '#1D4ED8',
            'secondary_color' => '#7C3AED',
            'theme_config'    => '{"layout":"compact"}',
        ]);

        $this->db->table('content_blocks')->insert([
            'site_id'       => $droitSiteId,
            'page_key'      => 'home',
            'type'          => 'custom_text',
            'title'         => 'Bloc droit isolé',
            'content'       => 'Contenu visible uniquement sur le site droit.',
            'settings'      => '{}',
            'display_order' => 99,
            'is_published'  => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        service('siteResolver')->reset();
        $droit = $this
            ->withHeaders(['Host' => 'droit.test'])
            ->get('/');

        $droit->assertOK();
        $droitBody = (string) $droit->response()->getBody();
        $this->assertStringContainsString('theme-modern', $droitBody);
        $this->assertStringContainsString('--green: #1D4ED8', $droitBody);
        $this->assertStringContainsString('Bloc droit isolé', $droitBody);
        $this->assertStringContainsString('Contenu visible uniquement sur le site droit.', $droitBody);

        $this->assertSame(0, $this->db->table('content_blocks')
            ->where('site_id', 1)
            ->where('title', 'Bloc droit isolé')
            ->countAllResults());

        service('siteResolver')->reset();
        $fseg = $this
            ->withHeaders(['Host' => 'localhost'])
            ->get('/');

        $fseg->assertOK();
        $fseg->assertDontSee('Bloc droit isolé');
        $fseg->assertDontSee('Contenu visible uniquement sur le site droit.');
    }

    private function createSecondSite(): int
    {
        $existing = $this->db->table('sites')->where('slug', 'droit')->get()->getRowArray();
        if (is_array($existing)) {
            return (int) $existing['id'];
        }

        $siteId = model(SiteModel::class, false)->insert([
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

        $this->assertNotFalse($siteId);

        return (int) $siteId;
    }

    private function copyHomeContentForSite(int $siteId, string $title): void
    {
        $home = $this->db->table('home_content')->where('site_id', 1)->get()->getRowArray();
        $this->assertIsArray($home);

        unset($home['id']);
        $home['site_id'] = $siteId;
        $home['hero_title'] = $title;
        $home['seo_title'] = $title;
        $home['created_at'] = date('Y-m-d H:i:s');
        $home['updated_at'] = date('Y-m-d H:i:s');

        $this->db->table('home_content')->insert($home);
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
        return $this->userWithGroup('multisite-super@example.test', 'multisitesuper', 'superadmin');
    }

    private function adminUser(): User
    {
        return $this->userWithGroup('multisite-admin@example.test', 'multisiteadmin', 'admin');
    }

    private function userWithGroup(string $email, string $username, string $group): User
    {
        $users = model(UserModel::class);
        $existing = $users->where('username', $username)->first();

        if ($existing instanceof User) {
            return $existing;
        }

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => 'MotDePasseMultisite!2026',
            'active'   => 1,
        ]);

        $users->save($user);

        /** @var User $created */
        $created = $users->findById($users->getInsertID());
        $created->addGroup($group);
        $created->activate();

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

    private function setEnvironment(string $key, string $value): void
    {
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }

    private function clearEnvironment(string $key): void
    {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }
}
