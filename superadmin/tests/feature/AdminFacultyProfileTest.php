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
final class AdminFacultyProfileTest extends CIUnitTestCase
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

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->get('/admin/faculty/profile')->assertRedirectTo(site_url('login'));
    }

    public function testAdminAccessWithoutPagesPermissionIsRejected(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-limited@example.test',
            'facultylimited',
            ['admin.access'],
        ));

        $this->get('/admin/faculty/profile')->assertRedirect();
    }

    public function testPagesManagerCanOpenFacultyProfileForActiveSite(): void
    {
        $this->actingAs($this->directPermissionUser(
            'faculty-pages@example.test',
            'facultypages',
            ['admin.access', 'pages.manage'],
        ));

        $result = $this->get('/admin/faculty/profile');

        $result->assertOK();
        $result->assertSee('Présentation / Mot du doyen');
        $result->assertSee('Faculté de démonstration');
        $result->assertSee('Photo du doyen');
        $result->assertSee('Versions française et anglaise');
    }

    public function testActiveSiteDataIsLoadedAndOnlyActiveSiteIsUpdated(): void
    {
        $siteId = $this->createSecondSite();
        $pageId = $this->insertFacultyPage($siteId, 'Doyen Droit Initial', 'Message droit initial.');
        $this->actingAs($this->superAdminUser());
        $this->selectAdminSite($siteId);

        $edit = $this->get('/admin/faculty/profile');
        $edit->assertOK();
        $edit->assertSee('Doyen Droit Initial');
        $edit->assertDontSee('Prof. Jean-Baptiste Ndayishimiye');

        $this->post('/admin/faculty/profile', $this->facultyPayload([
            'dean_name'    => 'Doyen Droit Modifié',
            'dean_message' => "Message droit modifié.\n\nDeuxième paragraphe.",
        ]))->assertRedirectTo(site_url('admin/faculty/profile'));

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
        $this->withUploadedFile('dean_photo', $file, 'dean.png', 'image/png');

        $this->post('/admin/faculty/profile', $this->facultyPayload([
            'dean_name' => 'Doyen Upload Valide',
        ]))->assertRedirectTo(site_url('admin/faculty/profile'));

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
        $this->withUploadedFile('dean_photo', $file, 'shell.php', 'application/x-php');

        $result = $this->post('/admin/faculty/profile', $this->facultyPayload([
            'dean_name' => 'Doyen Upload Rejeté',
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

        $this->post('/admin/faculty/profile', $this->facultyPayload([
            'dean_label'                   => 'MOT DU DOYEN TEST',
            'dean_title'                   => 'Bienvenue test FR',
            'dean_name'                    => 'Doyenne Test Public',
            'dean_role'                    => 'Doyenne FR fallback',
            'dean_specialty'               => 'Gestion publique',
            'dean_message'                 => "Message public français.\n\nSuite française.",
            'translation_en_dean_label'    => "DEAN'S TEST MESSAGE",
            'translation_en_dean_title'    => 'Welcome test EN',
            'translation_en_dean_role'     => '',
            'translation_en_dean_specialty'=> 'Public management',
            'translation_en_dean_message'  => "English public message.\n\nEnglish continuation.",
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
            'dean_label'                    => 'Mot du Doyen Test',
            'dean_title'                    => 'Bienvenue Test',
            'dean_name'                     => 'Doyen Test',
            'dean_role'                     => 'Doyen de la Faculté',
            'dean_specialty'                => 'Économie de test',
            'dean_signature'                => 'Doyen Test',
            'dean_message'                  => 'Message de test du doyen.',
            'translation_en_dean_label'     => 'Dean test message',
            'translation_en_dean_title'     => 'Welcome test',
            'translation_en_dean_role'      => 'Dean of the Faculty',
            'translation_en_dean_specialty' => 'Test economics',
            'translation_en_dean_signature' => 'Dean Test',
            'translation_en_dean_message'   => 'Dean test message body.',
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
