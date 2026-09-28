<?php

use App\Database\Seeds\TemplateStarterSeeder;
use App\Models\SiteModel;
use App\Support\InstanceCookieNames;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AdminTextSegmentsTest extends CIUnitTestCase
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
        $this->resetState();
    }

    protected function tearDown(): void
    {
        service('superglobals')->setFilesArray([]);
        $this->resetState();
        parent::tearDown();
    }

    private const DEAN_URL = 'admin/textes/faculte/mot-du-doyen';

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->get('/' . self::DEAN_URL)->assertRedirectTo(site_url('login'));
    }

    public function testOldFacultyProfileUrlRedirectsToDeanCategory(): void
    {
        $this->get('/admin/faculty/profile')->assertRedirectTo(site_url(self::DEAN_URL));
    }

    public function testAdminAccessWithoutPagesPermissionIsRejected(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-limited@example.test',
            'facultylimited',
            ['admin.access'],
        ));

        $this->get('/' . self::DEAN_URL)->assertRedirect();
    }

    public function testPagesManagerCanOpenDeanCategoryForActiveSite(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-pages@example.test',
            'facultypages',
            ['admin.access', 'pages.manage'],
        ));

        $result = $this->get('/' . self::DEAN_URL);

        $result->assertOK();
        $result->assertSee('Mot du doyen');
        $result->assertSee('Photo du doyen');
        $result->assertSee('English');
        $this->assertStringContainsString('Mission &amp; vision', (string) $result->response()->getBody());
    }

    public function testUnknownCategoryRedirects(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-unknown@example.test',
            'facultyunknown',
            ['admin.access', 'pages.manage'],
        ));

        $this->get('/admin/textes/faculte/inexistant')->assertRedirect();
    }

    public function testActiveSiteDataIsLoadedAndOnlyActiveSiteIsUpdated(): void
    {
        $siteId = $this->createSecondSite();
        $pageId = $this->insertFacultyPage($siteId, 'Doyen Droit Initial', 'Message droit initial.');
        $this->actingAs($this->superAdminUser());
        $this->selectAdminSite($siteId);

        $edit = $this->get('/' . self::DEAN_URL);
        $edit->assertOK();
        $edit->assertSee('Doyen Droit Initial');
        $edit->assertDontSee('Prof. Jean-Baptiste Ndayishimiye');

        $this->post('/' . self::DEAN_URL, $this->facultyPayload([
            'dean__name'       => 'Doyen Droit Modifié',
            'dean__paragraphs' => "Message droit modifié.\n\nDeuxième paragraphe.",
        ]))->assertRedirectTo(site_url(self::DEAN_URL));

        $updatedSiteTwo = $this->facultyContent($siteId);
        $siteOne = $this->facultyContent(1);

        $this->assertSame('Doyen Droit Modifié', $updatedSiteTwo['dean']['name'] ?? null);
        $this->assertSame(['Message droit modifié.', 'Deuxième paragraphe.'], $updatedSiteTwo['dean']['paragraphs'] ?? null);
        $this->assertNotSame('Doyen Droit Modifié', $siteOne['dean']['name'] ?? null);
        $this->assertSame($pageId, (int) $this->db->table('pages')->where('site_id', $siteId)->where('key', 'faculty')->get()->getRowArray()['id']);
    }

    public function testValidDeanPhotoUploadIsStoredForActiveSite(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-upload@example.test',
            'facultyupload',
            ['admin.access', 'pages.manage'],
        ));

        $file = $this->tempPng();
        $this->withUploadedFile('file_dean__photo', $file, 'dean.png', 'image/png');

        $this->post('/' . self::DEAN_URL, $this->facultyPayload([
            'dean__name' => 'Doyen Upload Valide',
        ]))->assertRedirectTo(site_url(self::DEAN_URL));

        $content = $this->facultyContent(1);
        $photo = (string) ($content['dean']['photo'] ?? '');

        $this->assertStringStartsWith('uploads/sites/fseg/faculty-deans/', $photo);
        $this->assertFileExists(FCPATH . $photo);
    }

    public function testInvalidDeanPhotoUploadIsRejected(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-invalid-upload@example.test',
            'facultyinvalidupload',
            ['admin.access', 'pages.manage'],
        ));

        $before = $this->facultyContent(1);
        $file = tempnam(sys_get_temp_dir(), 'site_bad_upload_');
        file_put_contents($file, '<?php echo "not an image";');
        $this->withUploadedFile('file_dean__photo', $file, 'shell.php', 'application/x-php');

        $result = $this->post('/' . self::DEAN_URL, $this->facultyPayload([
            'dean__name' => 'Doyen Upload Rejeté',
        ]));

        $result->assertRedirect();
        $result->assertSessionHas('errors');

        $after = $this->facultyContent(1);
        $this->assertSame($before['dean']['photo'] ?? null, $after['dean']['photo'] ?? null);
        $this->assertNotSame('Doyen Upload Rejeté', $after['dean']['name'] ?? null);

        @unlink($file);
    }

    public function testPublicFacultyPageUsesFrenchAndEnglishDeanContentWithFallback(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-public@example.test',
            'facultypublic',
            ['admin.access', 'pages.manage'],
        ));

        $this->post('/' . self::DEAN_URL, $this->facultyPayload([
            'dean__label'         => 'MOT DU DOYEN TEST',
            'dean__title'         => 'Bienvenue test FR',
            'dean__name'          => 'Doyenne Test Public',
            'dean__role'          => 'Doyenne FR fallback',
            'dean__specialty'     => 'Gestion publique',
            'dean__paragraphs'    => "Message public français.\n\nSuite française.",
            'en_dean__label'      => "DEAN'S TEST MESSAGE",
            'en_dean__title'      => 'Welcome test EN',
            'en_dean__role'       => '',
            'en_dean__specialty'  => 'Public management',
            'en_dean__paragraphs' => "English public message.\n\nEnglish continuation.",
        ]))->assertRedirect();

        $fr = $this->get('/faculte');
        $fr->assertOK();
        $fr->assertSee('MOT DU DOYEN TEST');
        $fr->assertSee('Bienvenue test FR');
        $fr->assertSee('Doyenne Test Public');
        $fr->assertSee('Message public français.');

        $en = $this->withLocaleCookie('en')->get('/faculte');
        $en->assertOK();
        $en->assertSee("DEAN'S TEST MESSAGE");
        $en->assertSee('Welcome test EN');
        $en->assertSee('English public message.');
        $en->assertSee('Doyenne FR fallback');
        $en->assertDontSee('Message public français.');
    }

    public function testSavingOneCategoryKeepsOtherCategoriesAndTranslations(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-isolation@example.test',
            'facultyisolation',
            ['admin.access', 'pages.manage'],
        ));

        $this->post('/' . self::DEAN_URL, $this->facultyPayload([
            'en_dean__title' => 'Isolation EN title',
        ]))->assertRedirect();
        $before = $this->facultyContent(1);

        $this->post('/admin/textes/faculte/historique', $this->withCsrf([
            'history__label'    => 'Parcours isolé',
            'history__title'    => 'Titre historique isolé',
            'history__text'     => 'Texte historique isolé.',
            'en_history__title' => 'Isolated history',
        ]))->assertRedirectTo(site_url('admin/textes/faculte/historique'));

        $after = $this->facultyContent(1);
        $this->assertSame('Titre historique isolé', $after['history']['title'] ?? null);
        $this->assertSame($before['dean'] ?? null, $after['dean'] ?? null);
        $this->assertSame($before['mission'] ?? null, $after['mission'] ?? null);

        $english = $this->facultyEnglish(1);
        $this->assertSame('Isolation EN title', $english['dean']['title'] ?? null);
        $this->assertSame('Isolated history', $english['history']['title'] ?? null);
        $this->assertSame('Parcours isolé', $english['history']['label'] ?? null);
    }

    public function testEmptyValueCardsAreRemoved(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-values@example.test',
            'facultyvalues',
            ['admin.access', 'pages.manage'],
        ));

        $this->post('/admin/textes/faculte/valeurs', $this->withCsrf([
            'values__0__icon'        => 'bi-award',
            'values__0__title'       => 'Excellence',
            'values__0__description' => 'Viser le meilleur.',
            'values__1__icon'        => '',
            'values__1__title'       => '',
            'values__1__description' => '',
            'values__2__icon'        => 'bi-heart',
            'values__2__title'       => 'Solidarité',
            'values__2__description' => '',
        ]))->assertRedirect();

        $values = $this->facultyContent(1)['values'] ?? [];
        $this->assertCount(2, $values);
        $this->assertSame(['Excellence', 'Solidarité'], array_column($values, 'title'));
        $this->assertCount(2, $this->facultyEnglish(1)['values'] ?? []);
    }

    public function testRequiredFieldAndInvalidIconAreRejected(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-required@example.test',
            'facultyrequired',
            ['admin.access', 'pages.manage'],
        ));

        $before = $this->facultyContent(1);
        $result = $this->post('/admin/textes/faculte/mission-vision', $this->withCsrf([
            'mission_label'        => '',
            'mission_title'        => 'Mission & Vision',
            'mission__icon'        => 'javascript:alert(1)',
            'mission__title'       => 'Mission',
            'mission__paragraphs'  => 'Texte',
            'vision__icon'         => 'bi-eye',
            'vision__title'        => 'Vision',
            'vision__paragraphs'   => 'Texte',
        ]));

        $result->assertRedirect();
        $result->assertSessionHas('errors');
        $this->assertSame($before['mission'] ?? null, $this->facultyContent(1)['mission'] ?? null);
    }

    public function testBannerTitleChangesHeadingButNotBreadcrumb(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-banner@example.test',
            'facultybanner',
            ['admin.access', 'pages.manage'],
        ));

        $this->post('/admin/textes/recherche/bandeau', $this->withCsrf([
            'banner_title'       => 'Nos laboratoires en action',
            'banner_subtitle'    => 'Sous-titre bandeau test',
            'en_banner_title'    => 'Our labs at work',
            'en_banner_subtitle' => '',
        ]))->assertRedirectTo(site_url('admin/textes/recherche/bandeau'));

        $fr = $this->get('/recherche');
        $fr->assertOK();
        $frBody = (string) $fr->response()->getBody();
        $this->assertStringContainsString('<h1>Nos laboratoires en action</h1>', $frBody);
        $this->assertStringContainsString('aria-current="page">' . esc(lang('Site.pageTitles.research')) . '</li>', $frBody);
        $fr->assertSee('Sous-titre bandeau test');

        $en = $this->withLocaleCookie('en')->get('/recherche');
        $en->assertOK();
        $this->assertStringContainsString('<h1>Our labs at work</h1>', (string) $en->response()->getBody());
        $en->assertSee('Sous-titre bandeau test');
    }

    public function testHomeCategorySavesColumnsAndEnglishTranslation(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-home@example.test',
            'facultyhome',
            ['admin.access', 'home.manage'],
        ));

        $this->post('/admin/textes/accueil/presentation', $this->withCsrf([
            'about_label'        => 'À propos test',
            'about_title'        => 'Titre présentation test',
            'about_body'         => 'Corps présentation test.',
            'about_button_label' => 'En savoir plus',
            'about_button_url'   => '/faculte',
            'en_about_title'     => 'About title test',
        ]))->assertRedirectTo(site_url('admin/textes/accueil/presentation'));

        $home = $this->db->table('home_content')->where('site_id', 1)->get()->getRowArray();
        $this->assertSame('Titre présentation test', $home['about_title'] ?? null);
        $this->assertSame(1, $this->db->table('content_translations')
            ->where('resource_type', 'home_content')
            ->where('resource_id', $home['id'])
            ->where('field', 'about_title')
            ->where('value', 'About title test')
            ->countAllResults());
    }

    private function resetState(): void
    {
        service('siteResolver')->reset();
        service('settingsService')->reset();
        service('contentTranslationService')->reset();
        service('superglobals')->setFilesArray([]);
        service('superglobals')->setCookieArray([]);

        try {
            auth('session')->getAuthenticator()->logout();
        } catch (Throwable) {
        }

        $_SESSION = [];
        $_COOKIE = [];
        $this->withSession([]);
    }

    /**
     * @param list<string> $permissions
     */
    private function directPermissionUser(string $email, string $username, array $permissions): User
    {
        return $this->user($email, $username, null, $permissions);
    }

    private function superAdminUser(): User
    {
        return $this->user('faculty-super@example.test', 'facultysuper', 'superadmin', []);
    }

    /**
     * @param list<string> $permissions
     */
    private function user(string $email, string $username, ?string $group, array $permissions): User
    {
        $users = model(UserModel::class);
        $existing = $users->where('username', $username)->first();

        if ($existing instanceof User) {
            return $existing;
        }

        $users->save(new User([
            'username' => $username,
            'email'    => $email,
            'password' => 'MotDePasseFaculty!2026',
            'active'   => 1,
        ]));

        /** @var User $created */
        $created = $users->findById($users->getInsertID());
        $created->removeGroup('editor', 'admin', 'superadmin');

        if ($group !== null) {
            $created->addGroup($group);
        }

        if ($permissions !== []) {
            $created->addPermission(...$permissions);
        }

        $created->activate();
        if ($group !== 'superadmin' && in_array('admin.access', $permissions, true)) {
            service('siteResolver')->syncUserSites((int) $created->id, [1], 'site_admin');
        }

        return $created;
    }

    private function createSecondSite(): int
    {
        $existing = $this->db->table('sites')->where('slug', 'droit-faculty-profile')->get()->getRowArray();
        if (is_array($existing)) {
            return (int) $existing['id'];
        }

        $siteId = model(SiteModel::class, false)->insert([
            'identifier'      => 'droit-faculty-profile',
            'name'            => 'Faculté de Droit',
            'slug'            => 'droit-faculty-profile',
            'hostnames'       => ['droit-faculty-profile.test'],
            'status'          => 'active',
            'default_locale'  => 'fr',
            'logo'            => 'assets/images/logo-placeholder.png',
            'primary_color'   => '#0D9B49',
            'secondary_color' => '#0B6F38',
            'contact_email'   => 'droit-profile@example.test',
            'phone'           => '+257 22 22 00 00',
            'address'         => 'Bujumbura',
        ], true);

        $this->assertNotFalse($siteId);

        return (int) $siteId;
    }

    private function insertFacultyPage(int $siteId, string $deanName, string $message): int
    {
        $existing = $this->db->table('pages')->where('site_id', $siteId)->where('key', 'faculty')->get()->getRowArray();
        if (is_array($existing)) {
            $content = json_decode((string) $existing['content'], true) ?: [];
            $content['dean']['name'] = $deanName;
            $content['dean']['paragraphs'] = [$message];
            $this->db->table('pages')->where('id', $existing['id'])->update([
                'content'    => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return (int) $existing['id'];
        }

        $content = [
            'banner_subtitle' => 'Faculté de Droit',
            'dean' => [
                'label'      => 'Mot du Doyen',
                'title'      => 'Bienvenue',
                'photo'      => '',
                'name'       => $deanName,
                'role'       => 'Doyen de la Faculté de Droit',
                'specialty'  => 'Droit économique',
                'signature'  => $deanName,
                'paragraphs' => [$message],
            ],
            'mission' => ['title' => 'Mission', 'paragraphs' => ['Mission droit.']],
            'vision'  => ['title' => 'Vision', 'paragraphs' => ['Vision droit.']],
            'values'  => [],
            'history' => ['label' => 'Historique', 'title' => 'Histoire', 'text' => 'Historique droit.'],
        ];

        $this->db->table('pages')->insert([
            'site_id'         => $siteId,
            'key'             => 'faculty',
            'title'           => 'Faculté de Droit',
            'slug'            => 'faculte',
            'content'         => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'seo_title'       => 'Faculté de Droit',
            'seo_description' => 'Présentation de la Faculté de Droit.',
            'is_published'    => 1,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function selectAdminSite(int $siteId): void
    {
        service('siteResolver')->selectAdminSite($siteId, auth()->user());
        service('siteResolver')->reset();
        service('settingsService')->reset();
        service('contentTranslationService')->reset();
        $_SESSION['active_admin_site_id'] = $siteId;
        $this->withSession(array_replace($_SESSION, ['active_admin_site_id' => $siteId]));
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function facultyPayload(array $overrides = []): array
    {
        return $this->withCsrf(array_replace([
            'dean__label'         => 'Mot du Doyen Test',
            'dean__title'         => 'Bienvenue Test',
            'dean__name'          => 'Doyen Test',
            'dean__role'          => 'Doyen de la Faculté',
            'dean__specialty'     => 'Économie de test',
            'dean__signature'     => 'Doyen Test',
            'dean__paragraphs'    => 'Message de test du doyen.',
            'en_dean__label'      => 'Dean test message',
            'en_dean__title'      => 'Welcome test',
            'en_dean__role'       => 'Dean of the Faculty',
            'en_dean__specialty'  => 'Test economics',
            'en_dean__signature'  => 'Dean Test',
            'en_dean__paragraphs' => 'Dean test message body.',
        ], $overrides));
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

    /**
     * @return array<string, mixed>
     */
    private function facultyContent(int $siteId): array
    {
        $page = $this->db->table('pages')->where('site_id', $siteId)->where('key', 'faculty')->get()->getRowArray();
        $this->assertIsArray($page);

        return json_decode((string) $page['content'], true) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    private function facultyEnglish(int $siteId): array
    {
        $page = $this->db->table('pages')->where('site_id', $siteId)->where('key', 'faculty')->get()->getRowArray();
        $this->assertIsArray($page);
        $row = $this->db->table('content_translations')
            ->where('resource_type', 'pages')
            ->where('resource_id', $page['id'])
            ->where('locale', 'en')
            ->where('field', 'content')
            ->get()
            ->getRowArray();

        return json_decode((string) ($row['value'] ?? ''), true) ?: [];
    }

    private function tempPng(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'site_dean_png_');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));

        return $path;
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

    private function withLocaleCookie(string $locale): self
    {
        service('superglobals')->setCookie(InstanceCookieNames::locale(), $locale);

        return $this;
    }
}
