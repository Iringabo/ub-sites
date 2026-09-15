<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AdminResourceCrudTest extends CIUnitTestCase
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
        $this->resetAuthState();
    }

    protected function tearDown(): void
    {
        $this->resetAuthState();
        parent::tearDown();
    }

    public function testPhaseFiveAdminIndexesLoadInFrenchForSuperAdmin(): void
    {
        $this->actingAs($this->superAdminUser());

        foreach ([
            '/admin/home-content'      => 'Textes des sections',
            '/admin/home-hero-slides'  => 'Héros (slides)',
            '/admin/home-highlights'   => 'Atouts de l’accueil',
            '/admin/site-stats'        => 'Statistiques',
            '/admin/programmes'        => 'Formations',
            '/admin/staff'             => 'Personnel',
            '/admin/laboratories'      => 'Laboratoires',
            '/admin/publications'      => 'Publications scientifiques',
            '/admin/research-projects' => 'Projets de recherche',
            '/admin/timeline-items'    => 'Historique',
            '/admin/alumni-profiles'   => 'Profils alumni',
            '/admin/testimonials'      => 'Témoignages',
            '/admin/pages'             => 'Pages modifiables',
            '/admin/settings'          => 'Paramètres',
        ] as $uri => $expectedText) {
            $result = $this->get($uri);

            $result->assertOK();
            $result->assertSee($expectedText);
            $result->assertSee('Administration');
        }
    }

    public function testResourcePermissionIsRequiredBeyondAdminAccess(): void
    {
        $this->actingAs($this->directPermissionUser(
            'phase5-limited@example.test',
            'phase5limited',
            ['admin.access'],
        ));

        $this->get('/admin/programmes')->assertRedirect();
        $this->assertSame(0, $this->db->table('programmes')->where('title', 'Formation non autorisée')->countAllResults());
    }

    public function testEventsManagerCanReachCombinedPostsModuleFromSidebar(): void
    {
        $this->actingAs($this->directPermissionUser(
            'phase5-events@example.test',
            'phase5events',
            ['admin.access', 'events.manage'],
        ));

        $result = $this->get('/admin/posts');

        $result->assertOK();
        $result->assertSee('Actualités et événements');
        $result->assertSee('admin-sidebar');
    }

    public function testProgrammeCrudKeepsModuleIndependentFromStaff(): void
    {
        $this->actingAs($this->superAdminUser('phase5-programmes@example.test', 'phase5programmes'));

        $this->post('/admin/programmes', $this->withCsrf([
            'level'                => 'master',
            'title'                => 'Master Phase 5 indépendant',
            'slug'                 => '',
            'duration'             => '2 ans',
            'summary'              => 'Résumé de la formation Phase 5.',
            'description'          => 'Description complète de la formation Phase 5.',
            'admission_conditions' => 'Licence ou équivalent.',
            'career_outcomes'      => "Analyste économique\nResponsable financier",
            'display_order'        => '41',
            'featured_on_home'     => '1',
            'home_order'           => '7',
            'is_published'         => '1',
        ]))->assertRedirect();

        $programme = $this->db->table('programmes')
            ->where('title', 'Master Phase 5 indépendant')
            ->get()
            ->getRowArray();

        $this->assertIsArray($programme);
        $this->assertSame('master-phase-5-independant', $programme['slug']);
        $this->assertArrayNotHasKey('staff_id', $programme);
        $this->assertSame('1', (string) $programme['featured_on_home']);

        $edit = $this->get('/admin/programmes/' . $programme['id'] . '/edit');
        $edit->assertOK();
        $edit->assertSee('Débouchés');
        $edit->assertDontSee('coordinateur');

        $this->post('/admin/programmes/' . $programme['id'], $this->withCsrf([
            'level'                => 'master',
            'title'                => 'Master Phase 5 mis à jour',
            'slug'                 => $programme['slug'],
            'duration'             => '2 ans',
            'summary'              => 'Résumé mis à jour.',
            'description'          => 'Description mise à jour.',
            'admission_conditions' => 'Licence ou équivalent.',
            'career_outcomes'      => "Conseiller\nAuditeur",
            'display_order'        => '42',
            'featured_on_home'     => '0',
            'home_order'           => '',
            'is_published'         => '1',
        ]))->assertRedirect();

        $updated = $this->db->table('programmes')->where('id', $programme['id'])->get()->getRowArray();
        $this->assertSame('Master Phase 5 mis à jour', $updated['title']);
        $this->assertSame('0', (string) $updated['featured_on_home']);

        $this->post('/admin/programmes/' . $programme['id'] . '/delete', $this->withCsrf())->assertRedirect();
        $deleted = $this->db->table('programmes')->where('id', $programme['id'])->get()->getRowArray();
        $this->assertNotEmpty($deleted['deleted_at']);

        $trash = $this->get('/admin/programmes?trash=1');
        $trash->assertOK();
        $trash->assertSee('Corbeille');
        $trash->assertSee('Master Phase 5 mis à jour');
        $trash->assertSee('Restaurer');

        $this->post('/admin/programmes/' . $programme['id'] . '/restore', $this->withCsrf())->assertRedirect();
        $restored = $this->db->table('programmes')->where('id', $programme['id'])->get()->getRowArray();
        $this->assertEmpty($restored['deleted_at']);

        $this->post('/admin/programmes/' . $programme['id'] . '/delete', $this->withCsrf())->assertRedirect();
        $this->post('/admin/programmes/' . $programme['id'] . '/purge', $this->withCsrf())->assertRedirect();
        $this->assertSame(0, $this->db->table('programmes')->where('id', $programme['id'])->countAllResults());
    }

    public function testResearchProjectCrudKeepsModuleIndependentFromLaboratories(): void
    {
        $this->actingAs($this->superAdminUser('phase5-research@example.test', 'phase5research'));

        $this->post('/admin/research-projects', $this->withCsrf([
            'code'          => 'PHASE5-' . bin2hex(random_bytes(2)),
            'title'         => 'Projet Phase 5 sans laboratoire',
            'description'   => 'Projet créé sans rattachement obligatoire à un laboratoire.',
            'funder'        => 'FSEG',
            'period_start'  => '2026',
            'period_end'    => '2028',
            'icon'          => 'bi-diagram-3',
            'display_order' => '51',
            'is_published'  => '1',
        ]))->assertRedirect();

        $project = $this->db->table('research_projects')
            ->where('title', 'Projet Phase 5 sans laboratoire')
            ->get()
            ->getRowArray();

        $this->assertIsArray($project);
        $this->assertArrayNotHasKey('laboratory_id', $project);

        $edit = $this->get('/admin/research-projects/' . $project['id'] . '/edit');
        $edit->assertOK();
        $edit->assertSee('Bailleur');
        $edit->assertDontSee('laboratory_id');

        $this->post('/admin/research-projects/' . $project['id'] . '/delete', $this->withCsrf())->assertRedirect();
        $this->assertSame(
            0,
            $this->db->table('research_projects')->where('id', $project['id'])->countAllResults(),
        );
    }

    public function testHomeContentHighlightsStatsAndSettingsAreEditable(): void
    {
        $this->actingAs($this->superAdminUser('phase5-home@example.test', 'phase5home'));

        $home = $this->db->table('home_content')->where('singleton_key', 1)->get()->getRowArray();
        $this->assertIsArray($home);

        $this->post('/admin/home-content/' . $home['id'], $this->withCsrf([
            'about_label'            => 'Présentation',
            'about_title'            => 'Présentation administrable',
            'about_body'             => 'Texte de présentation administrable.',
            'about_button_label'     => 'Lire',
            'about_button_url'       => '/faculte',
            'research_label'         => 'Recherche',
            'research_title'         => 'Recherche administrable',
            'research_body'          => 'Texte recherche administrable.',
            'research_button_label'  => 'Explorer',
            'research_button_url'    => '/recherche',
            'programmes_label'       => 'Programmes administrables',
            'programmes_title'       => 'Formations administrables',
            'programmes_text'        => 'Texte formations administrable.',
            'programmes_button_label'=> 'Voir les formations',
            'programmes_button_url'  => '/formations',
            'posts_label'            => 'Actualités administrables',
            'posts_title'            => 'Actualités et événements administrables',
            'posts_text'             => 'Texte actualités administrable.',
            'posts_button_label'     => 'Voir les actualités',
            'posts_button_url'       => '/actualites',
            'seo_title'              => 'Accueil administrable FSEG',
            'seo_description'        => 'Description SEO administrable.',
        ]))->assertRedirect();

        $updatedHome = $this->db->table('home_content')->where('id', $home['id'])->get()->getRowArray();
        $this->assertSame('Présentation administrable', $updatedHome['about_title']);
        $this->assertSame('Programmes administrables', $updatedHome['programmes_label']);
        $this->assertSame('Actualités administrables', $updatedHome['posts_label']);
        $this->assertNotEmpty($updatedHome['updated_by']);

        $this->post('/admin/home-highlights', $this->withCsrf([
            'icon'          => 'bi-stars',
            'title'         => 'Atout Phase 5',
            'description'   => 'Atout créé depuis l’administration.',
            'display_order' => '25',
            'is_published'  => '1',
        ]))->assertRedirect();
        $highlight = $this->db->table('home_highlights')->where('title', 'Atout Phase 5')->get()->getRowArray();
        $this->assertIsArray($highlight);

        $highlightEdit = $this->get('/admin/home-highlights/' . $highlight['id'] . '/edit');
        $highlightEdit->assertOK();
        $highlightEdit->assertSee('admin-icon-picker');
        $highlightEdit->assertSee('Atout');
        $highlightEdit->assertDontSee('Icône Bootstrap');

        $this->post('/admin/site-stats', $this->withCsrf([
            'section'       => 'home_main',
            'label'         => 'Stat Phase 5',
            'value'         => '123',
            'suffix'        => '+',
            'display_order' => '26',
            'is_published'  => '1',
        ]))->assertRedirect();
        $this->assertSame(1, $this->db->table('site_stats')->where('label', 'Stat Phase 5')->countAllResults());

        $setting = $this->db->table('settings')->where('key', 'contact.email')->get()->getRowArray();
        $this->assertIsArray($setting);

        $settingsEdit = $this->get('/admin/settings/' . $setting['id'] . '/edit');
        $settingsEdit->assertOK();
        $settingsEdit->assertSee('Adresse électronique');
        $settingsEdit->assertDontSee('Classe');
        $settingsEdit->assertDontSee('Type');

        $this->post('/admin/settings/' . $setting['id'], $this->withCsrf([
            'key'   => 'contact.email',
            'value' => 'contact-phase5@ub.edu.bi',
        ]))->assertRedirect();

        $updatedSetting = $this->db->table('settings')->where('id', $setting['id'])->get()->getRowArray();
        $this->assertSame('contact-phase5@ub.edu.bi', $updatedSetting['value']);
        $this->assertSame('email', $updatedSetting['type']);
        $this->assertSame('contact', $updatedSetting['context']);

        $badEmail = $this->post('/admin/settings/' . $setting['id'], $this->withCsrf([
            'key'   => 'contact.email',
            'value' => 'adresse-invalide',
        ]));

        $badEmail->assertRedirect();
        $badEmail->assertSessionHas('errors');

        $this->get('/admin/settings/new')->assertRedirect();

        $this->post('/admin/settings', $this->withCsrf([
            'key'   => 'api_token_phase5',
            'value' => 'ne-pas-stocker',
        ]))->assertRedirect();
        $this->assertSame(0, $this->db->table('settings')->where('key', 'api_token_phase5')->countAllResults());
    }

    public function testPageEditorUsesStructuredFieldsAndSavesEnglishFallbackContent(): void
    {
        $this->actingAs($this->superAdminUser('phase5-pages@example.test', 'phase5pages'));

        $page = $this->db->table('pages')->where('key', 'posts')->get()->getRowArray();
        $this->assertIsArray($page);

        $edit = $this->get('/admin/pages/' . $page['id'] . '/edit');
        $edit->assertOK();
        $edit->assertSee('Sous-titre du bandeau');
        $edit->assertDontSee('{&quot;banner_subtitle&quot;');

        $this->post('/admin/pages/' . $page['id'], $this->withCsrf([
            'key'                                            => 'posts',
            'title'                                          => 'Actualités et événements Phase 5',
            'slug'                                           => 'actualites',
            'page_content_banner_subtitle'                   => 'Nouvelles vérifiées de la faculté',
            'seo_title'                                      => 'Actualités Phase 5',
            'seo_description'                                => 'Description Phase 5',
            'is_published'                                   => '1',
            'translation_en_page_content_banner_subtitle'    => 'Verified faculty updates',
            'translation_en_title'                           => 'News and events',
            'translation_en_seo_title'                       => 'News Phase 5',
            'translation_en_seo_description'                 => 'Phase 5 description',
        ]))->assertRedirect();

        $updated = $this->db->table('pages')->where('id', $page['id'])->get()->getRowArray();
        $content = json_decode((string) $updated['content'], true);
        $this->assertSame('Nouvelles vérifiées de la faculté', $content['banner_subtitle'] ?? null);

        $translation = $this->db->table('content_translations')
            ->where('resource_type', 'pages')
            ->where('resource_id', $page['id'])
            ->where('locale', 'en')
            ->where('field', 'content')
            ->get()
            ->getRowArray();

        $this->assertIsArray($translation);
        $translatedContent = json_decode((string) $translation['value'], true);
        $this->assertSame('Verified faculty updates', $translatedContent['banner_subtitle'] ?? null);
    }

    public function testAdminRejectsUnsafeExternalPublicationUrl(): void
    {
        $this->actingAs($this->superAdminUser('phase5-url@example.test', 'phase5url'));

        $result = $this->post('/admin/publications', $this->withCsrf([
            'year'          => '2026',
            'title'         => 'Publication URL dangereuse',
            'authors'       => 'Équipe FSEG',
            'journal'       => 'Revue de test',
            'url'           => 'javascript://alert(1)',
            'display_order' => '71',
            'is_published'  => '1',
        ]));

        $result->assertRedirect();
        $result->assertSessionHas('errors');
        $this->assertSame(0, $this->db->table('publications')->where('title', 'Publication URL dangereuse')->countAllResults());
    }

    private function resetAuthState(): void
    {
        try {
            auth('session')->getAuthenticator()->logout();
        } catch (Throwable) {
        }

        $_SESSION = [];
        $this->withSession([]);
    }

    private function superAdminUser(string $email = 'phase5-super@example.test', string $username = 'phase5super'): User
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
            'password' => 'MotDePassePhase5!2026',
            'active'   => 1,
        ]);

        $users->save($user);

        /** @var User $created */
        $created = $users->findById($users->getInsertID());
        $created->addGroup('superadmin');
        $created->activate();

        return $created;
    }

    /**
     * @param list<string> $permissions
     */
    private function directPermissionUser(string $email, string $username, array $permissions): User
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
            'password' => 'MotDePassePhase5!2026',
            'active'   => 1,
        ]);

        $users->save($user);

        /** @var User $created */
        $created = $users->findById($users->getInsertID());
        $created->removeGroup('editor', 'admin', 'superadmin');
        $created->addPermission(...$permissions);
        $created->activate();
        service('siteResolver')->syncUserSites((int) $created->id, [1], 'site_admin');

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
        $token = $security->getTokenName();
        $hash = $security->generateHash();

        $this->withSession(array_replace($_SESSION, [$token => $hash]));

        return array_replace([$token => $hash], $data);
    }
}
