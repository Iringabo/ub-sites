<?php

use App\Database\Seeds\TemplateStarterSeeder;
use App\Services\HomePageService;
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
            '/admin/home-hero-slides'  => 'Carrousel d’images',
            '/admin/site-stats'        => 'Statistiques',
            '/admin/programmes'        => 'Formations',
            '/admin/staff'             => 'Personnel',
            '/admin/laboratories'      => 'Laboratoires',
            '/admin/publications'      => 'Publications scientifiques',
            '/admin/research-projects' => 'Projets de recherche',
            '/admin/timeline-items'    => 'Historique',
            '/admin/alumni-profiles'   => 'Profils alumni',
            '/admin/testimonials'      => 'Témoignages',
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

    public function testProgrammeCommonCoreRequiresADepartment(): void
    {
        $this->actingAs($this->superAdminUser('options-programmes@example.test', 'optionsprogrammes'));

        $fields = [
            'level'                => 'licence',
            'title'                => 'Licence Option Tronc',
            'slug'                 => '',
            'option_code'          => 'GI',
            'common_core'          => 'Tronc commun Informatique',
            'duration'             => '3 ans',
            'summary'              => 'Résumé de l’option.',
            'description'          => 'Description de l’option.',
            'admission_conditions' => 'Diplôme d’État.',
            'career_outcomes'      => 'Administrateur systèmes',
            'display_order'        => '5',
            'is_published'         => '1',
        ];

        $rejected = $this->post('/admin/programmes', $this->withCsrf($fields + ['department_id' => '']));
        $rejected->assertRedirect();
        $rejected->assertSessionHas('errors');
        $this->assertArrayHasKey('common_core', $_SESSION['errors']);
        $this->assertSame(0, $this->db->table('programmes')->where('title', 'Licence Option Tronc')->countAllResults());

        $this->db->table('departments')->insert([
            'site_id' => 1, 'name' => 'Département TIC test', 'slug' => 'tic-options-test', 'abbreviation' => 'TIC',
            'cycle' => 'both', 'display_order' => 1, 'is_published' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $departmentId = (int) $this->db->insertID();

        $this->post('/admin/programmes', $this->withCsrf($fields + ['department_id' => (string) $departmentId]))->assertRedirect();

        $saved = $this->db->table('programmes')->where('title', 'Licence Option Tronc')->get()->getRowArray();
        $this->assertIsArray($saved);
        $this->assertSame('GI', $saved['option_code']);
        $this->assertSame('Tronc commun Informatique', $saved['common_core']);
        $this->assertSame($departmentId, (int) $saved['department_id']);

        $edit = $this->get('/admin/programmes/' . $saved['id'] . '/edit');
        $edit->assertOK();
        $edit->assertSee('Sigle de l’option');
        $edit->assertSee('common_core_suggestions');
        $edit->assertSee('<option value="Tronc commun Informatique">');
    }

    public function testProgrammeStudyPathFieldsAreValidated(): void
    {
        $this->actingAs($this->superAdminUser('study-paths@example.test', 'studypaths'));

        $this->db->table('departments')->insert([
            'site_id' => 1, 'name' => 'Département Parcours test', 'slug' => 'parcours-test', 'abbreviation' => 'PRC',
            'cycle' => 'both', 'display_order' => 1, 'is_published' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $departmentId = (string) $this->db->insertID();

        $fields = [
            'level'                => 'licence',
            'title'                => 'Licence Parcours Test',
            'slug'                 => '',
            'department_id'        => $departmentId,
            'option_code'          => 'GL',
            'duration'             => '4 ans',
            'summary'              => 'Résumé.',
            'description'          => 'Description.',
            'admission_conditions' => 'Diplôme d’État.',
            'career_outcomes'      => 'Développeur',
            'display_order'        => '5',
            'is_published'         => '1',
        ];
        $rejects = function (array $extra, string $field) use ($fields): void {
            $_SESSION = [];
            $result = $this->post('/admin/programmes', $this->withCsrf($extra + $fields));
            $result->assertRedirect();
            $result->assertSessionHas('errors');
            $this->assertArrayHasKey($field, $_SESSION['errors']);
            $this->assertSame(0, $this->db->table('programmes')->where('title', 'Licence Parcours Test')->countAllResults());
        };

        $rejects(['branch_core' => 'Tronc commun GI-GL'], 'branch_core');
        $rejects(['common_core_years' => '1'], 'common_core_years');
        $rejects(['common_core' => 'Tronc commun TIC', 'branch_core_years' => '1'], 'branch_core_years');
        $rejects(['common_core' => 'Tronc commun TIC', 'common_core_years' => '3', 'branch_core' => 'Tronc commun GI-GL'], 'branch_core_years');
        $rejects(['common_core' => 'Tronc commun TIC', 'common_core_years' => '9'], 'common_core_years');
        $rejects(['level' => 'doctorat'], 'level');

        $_SESSION = [];
        $this->post('/admin/programmes', $this->withCsrf([
            'common_core'       => 'Tronc commun TIC',
            'common_core_years' => '1',
            'branch_core'       => 'Tronc commun GI-GL',
            'branch_core_years' => '1',
        ] + $fields))->assertRedirect();

        $saved = $this->db->table('programmes')->where('title', 'Licence Parcours Test')->get()->getRowArray();
        $this->assertIsArray($saved);
        $this->assertSame(1, (int) $saved['common_core_years']);
        $this->assertSame('Tronc commun GI-GL', $saved['branch_core']);
        $this->assertSame(1, (int) $saved['branch_core_years']);

        $form = $this->get('/admin/programmes/new');
        $form->assertOK();
        $form->assertSee('Parcours d’études');
        $form->assertSee('branch_core_suggestions');
        $form->assertDontSee('<option value="doctorat"');
    }

    public function testDepartmentMaquetteUploadAcceptsOnlyPdf(): void
    {
        $this->actingAs($this->superAdminUser('maquette-upload@example.test', 'maquetteupload'));

        $fields = ['name' => 'Département Maquette Upload', 'slug' => 'maquette-upload', 'cycle' => 'licence', 'maquette_title' => 'Maquettes 2024-2025', 'display_order' => '1', 'is_published' => '1'];

        $fake = tempnam(sys_get_temp_dir(), 'maquette_bad_');
        file_put_contents($fake, '<?php echo "not a pdf";');
        $this->withUploadedFile('maquette_path', $fake, 'maquette.pdf', 'application/pdf');
        $rejected = $this->post('/admin/departments', $this->withCsrf($fields));
        $rejected->assertRedirect();
        $rejected->assertSessionHas('errors');
        $this->assertArrayHasKey('maquette_path', $_SESSION['errors']);
        $this->assertSame(0, $this->db->table('departments')->where('slug', 'maquette-upload')->countAllResults());

        $_SESSION = [];
        $pdf = tempnam(sys_get_temp_dir(), 'maquette_ok_');
        file_put_contents($pdf, "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n");
        $this->withUploadedFile('maquette_path', $pdf, 'maquette.pdf', 'application/pdf');
        $this->post('/admin/departments', $this->withCsrf($fields))->assertRedirect();
        service('superglobals')->setFilesArray([]);

        $saved = $this->db->table('departments')->where('slug', 'maquette-upload')->get()->getRowArray();
        $this->assertIsArray($saved);
        $this->assertStringStartsWith('uploads/sites/', (string) $saved['maquette_path']);
        $this->assertStringContainsString('/maquettes/', (string) $saved['maquette_path']);
        $this->assertStringEndsWith('.pdf', (string) $saved['maquette_path']);
        $this->assertFileExists(FCPATH . $saved['maquette_path']);
        $this->assertSame('Maquettes 2024-2025', $saved['maquette_title']);

        $edit = $this->get('/admin/departments/' . $saved['id'] . '/edit');
        $edit->assertOK();
        $edit->assertSee('Maquette des cours (PDF)');
        $edit->assertSee('remove_maquette_path');

        @unlink(FCPATH . $saved['maquette_path']);
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

        $retired = $this->get('/admin/home-content');
        $retired->assertRedirectTo(site_url('admin/textes/accueil'));
        $this->post('/admin/home-content/' . $home['id'], $this->withCsrf([
            'about_title' => 'Présentation par l’ancien formulaire',
        ]))->assertRedirect();
        $this->assertSame(
            $home['about_title'],
            $this->db->table('home_content')->where('id', $home['id'])->get()->getRowArray()['about_title'],
        );

        $this->post('/admin/textes/accueil/bloc-formations', $this->withCsrf([
            'programmes_label'        => 'Programmes administrables',
            'programmes_title'        => 'Formations administrables',
            'programmes_text'         => 'Texte formations administrable.',
            'programmes_button_label' => 'Voir les formations',
            'programmes_button_url'   => '/formations',
        ]))->assertRedirect();

        $updatedHome = $this->db->table('home_content')->where('id', $home['id'])->get()->getRowArray();
        $this->assertSame('Programmes administrables', $updatedHome['programmes_label']);
        $this->assertSame($home['about_title'], $updatedHome['about_title']);
        $this->assertSame($home['posts_label'], $updatedHome['posts_label']);

        $this->post('/admin/home-highlights', $this->withCsrf([
            'icon'          => 'bi-stars',
            'title'         => 'Atout Phase 5',
            'description'   => 'Atout créé depuis l’administration.',
            'display_order' => '25',
            'is_published'  => '1',
        ]))->assertRedirect();
        $this->assertSame(0, $this->db->table('home_highlights')->where('title', 'Atout Phase 5')->countAllResults());

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
        $edit->assertRedirect();
        $edit->assertSessionHas('error');
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

    public function testStaffHomeFeatureFieldsControlHomepagePreview(): void
    {
        $this->actingAs($this->superAdminUser('phase5-staff-home@example.test', 'phase5staffhome'));

        $form = $this->get('/admin/staff/new');
        $form->assertOK();
        $form->assertSee('Mis en avant sur l’accueil');
        $form->assertSee('Ordre sur l’accueil');

        $this->post('/admin/staff', $this->withCsrf([
            'category'         => 'enseignant',
            'name'             => 'Personne mise en avant',
            'slug'             => '',
            'grade'            => 'Professeur',
            'specialty'        => 'Économie',
            'role'             => '',
            'email'            => '',
            'biography'        => 'Bio mise en avant.',
            'display_order'    => '10',
            'featured_on_home' => '1',
            'home_order'       => '1',
            'is_published'     => '1',
        ]))->assertRedirect();

        $this->post('/admin/staff', $this->withCsrf([
            'category'         => 'enseignant',
            'name'             => 'Personne hors accueil',
            'slug'             => '',
            'grade'            => 'Assistant',
            'specialty'        => 'Gestion',
            'role'             => '',
            'email'            => '',
            'biography'        => 'Bio hors accueil.',
            'display_order'    => '1',
            'featured_on_home' => '0',
            'home_order'       => '',
            'is_published'     => '1',
        ]))->assertRedirect();

        $featured = $this->db->table('staff')->where('name', 'Personne mise en avant')->get()->getRowArray();
        $hidden   = $this->db->table('staff')->where('name', 'Personne hors accueil')->get()->getRowArray();
        $this->assertIsArray($featured);
        $this->assertIsArray($hidden);
        $this->assertSame('1', (string) $featured['featured_on_home']);
        $this->assertSame('0', (string) $hidden['featured_on_home']);

        $preview = (new HomePageService())->data()['featuredStaff'];
        $names   = array_map(static fn (object $member): string => (string) $member->name, $preview);

        $this->assertContains('Personne mise en avant', $names);
        $this->assertNotContains('Personne hors accueil', $names);
    }

    private function withUploadedFile(string $field, string $path, string $name, string $type): void
    {
        service('superglobals')->setFilesArray([
            $field => [
                'name'     => $name,
                'type'     => $type,
                'tmp_name' => $path,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($path),
            ],
        ]);
    }

    private function resetAuthState(): void
    {
        try {
            auth('session')->getAuthenticator()->logout();
        } catch (Throwable) {
        }

        $_SESSION = [];
        $this->withSession([]);
        service('superglobals')->setFilesArray([]);
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
