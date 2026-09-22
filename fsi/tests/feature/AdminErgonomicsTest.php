<?php

use App\Database\Seeds\TemplateStarterSeeder;
use App\Entities\Setting;
use App\Models\SettingModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AdminErgonomicsTest extends CIUnitTestCase
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

    public function testSettingsOverviewGroupsAllContextsOnOnePage(): void
    {
        $this->actingAs($this->superAdminUser());

        $result = $this->get('/admin/settings/global');

        $result->assertOK();
        $result->assertSee('Coordonnées &amp; identité');
        foreach (['Institution', 'Coordonnées', 'Pied de page', 'Identité visuelle', 'Référencement (SEO)', 'Accueil'] as $section) {
            $result->assertSee($section);
        }
        $result->assertSee('Titre SEO par défaut');
        $result->assertSee('Taille des pastilles du carrousel');
    }

    public function testSettingsUpdatePersistsAValue(): void
    {
        $this->actingAs($this->superAdminUser());

        $this->post('/admin/settings/global', $this->withCsrf([
            'setting_institution_faculty_name' => 'Faculté de test des coordonnées',
            'setting_institution_short_name'   => 'FT',
            'setting_institution_university'   => 'Université du Burundi',
            'setting_contact_address_line'     => 'Avenue de la Révolution',
            'setting_contact_address_commune'  => 'Mukaza',
            'setting_contact_address_province' => 'Bujumbura Mairie',
            'setting_contact_address_country'  => 'Burundi',
            'setting_contact_phone'            => '+257 00 00 00 00',
            'setting_contact_email'            => 'contact@example.test',
            'setting_contact_hours'            => 'Lun-Ven 8h-17h',
            'setting_footer_text'              => 'Pied de page de test',
            'setting_footer_copyright'         => '© 2026 Faculté de test',
            'setting_seo_default_title'        => 'Titre SEO de test',
            'setting_seo_default_description'  => 'Description SEO de test.',
            'setting_seo_theme_color'          => '#0D9B49',
        ]))->assertRedirect();

        $row = $this->db->table('settings')
            ->where('key', 'institution.faculty_name')
            ->get()
            ->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame('Faculté de test des coordonnées', $row['value']);
    }

    public function testSettingsUpsertFailureFlashesError(): void
    {
        $this->actingAs($this->superAdminUser());

        $mock = new class () extends SettingModel {
            public function forSite(?int $siteId = null): static
            {
                return $this;
            }

            public function where($key = null, $value = null, ?bool $escape = null)
            {
                return $this;
            }

            public function first()
            {
                return new Setting(['id' => 1, 'key' => 'institution.faculty_name']);
            }

            public function skipValidation(bool $skip = true)
            {
                return $this;
            }

            public function update($id = null, $row = null): bool
            {
                throw new RuntimeException('forced settings failure');
            }
        };

        Factories::injectMock('models', SettingModel::class, $mock);

        try {
            $response = $this->post('/admin/settings/global', $this->withCsrf([
                'setting_institution_faculty_name' => 'Faculté de test des coordonnées',
                'setting_institution_short_name'   => 'FT',
                'setting_institution_university'   => 'Université du Burundi',
                'setting_contact_address_line'     => 'Avenue de la Révolution',
                'setting_contact_address_commune'  => 'Mukaza',
                'setting_contact_address_province' => 'Bujumbura Mairie',
                'setting_contact_address_country'  => 'Burundi',
                'setting_contact_phone'            => '+257 00 00 00 00',
                'setting_contact_email'            => 'contact@example.test',
                'setting_contact_hours'            => 'Lun-Ven 8h-17h',
                'setting_footer_text'              => 'Pied de page de test',
                'setting_footer_copyright'         => '© 2026 Faculté de test',
                'setting_seo_default_title'        => 'Titre SEO de test',
                'setting_seo_default_description'  => 'Description SEO de test.',
                'setting_seo_theme_color'          => '#0D9B49',
            ]));

            $response->assertRedirect();
            $response->assertSessionHas('errors');
            $response->assertSessionMissing('message');
        } finally {
            Factories::reset('models');
        }
    }

    public function testBulkPublishUpdatesAllSelectedProgrammes(): void
    {
        $this->actingAs($this->superAdminUser());

        $ids = array_map('intval', array_column(
            $this->db->table('programmes')->select('id')->orderBy('id', 'ASC')->limit(3)->get()->getResultArray(),
            'id',
        ));
        $this->assertNotEmpty($ids);

        $this->db->table('programmes')->whereIn('id', $ids)->update(['is_published' => 0]);

        $this->post('/admin/programmes/bulk', $this->withCsrf([
            'bulk_action' => 'publish',
            'ids'         => $ids,
        ]))->assertRedirect();

        foreach ($ids as $id) {
            $row = $this->db->table('programmes')->where('id', $id)->get()->getRowArray();
            $this->assertSame('1', (string) $row['is_published']);
        }
    }

    public function testBulkArchiveWithoutUpdateKeepsRowsRestorable(): void
    {
        $this->actingAs($this->superAdminUser());

        $ids = array_map('intval', array_column(
            $this->db->table('programmes')->select('id')->orderBy('id', 'ASC')->limit(2)->get()->getResultArray(),
            'id',
        ));
        $this->assertNotEmpty($ids);

        $this->post('/admin/programmes/bulk', $this->withCsrf([
            'bulk_action' => 'delete',
            'ids'         => $ids,
        ]))->assertRedirect();

        foreach ($ids as $id) {
            $row = $this->db->table('programmes')->where('id', $id)->get()->getRowArray();
            $this->assertNotEmpty($row['deleted_at']);
        }
    }

    public function testDuplicateCreatesUnpublishedCopyWithUniqueSlug(): void
    {
        $this->actingAs($this->superAdminUser());

        $ids = array_map('intval', array_column(
            $this->db->table('programmes')
                ->select('id')
                ->where('deleted_at', null)
                ->orderBy('id', 'ASC')
                ->limit(1)
                ->get()
                ->getResultArray(),
            'id',
        ));
        $this->assertNotEmpty($ids);
        $sourceId = $ids[0];
        $source = $this->db->table('programmes')->where('id', $sourceId)->get()->getRowArray();

        $this->post('/admin/programmes/' . $sourceId . '/duplicate', $this->withCsrf())->assertRedirect();

        $copies = $this->db->table('programmes')
            ->where('title', $source['title'])
            ->where('id !=', $sourceId)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();
        $this->assertNotEmpty($copies);
        $copy = $copies[0];

        $this->assertSame('0', (string) $copy['is_published']);
        $this->assertNotSame($source['slug'], $copy['slug']);
    }

    public function testReorderUpdatesDisplayOrderFromSequence(): void
    {
        $this->actingAs($this->superAdminUser());

        $rows = $this->db->table('site_stats')->select('id')->orderBy('display_order', 'ASC')->limit(3)->get()->getResultArray();
        $ids = array_map('intval', array_column($rows, 'id'));
        $this->assertCount(3, $ids);

        $reversed = array_reverse($ids);
        $result = $this->post('/admin/site-stats/reorder', $this->withCsrf(['ids' => $reversed]));
        $result->assertJSONExact(['ok' => true]);

        foreach ($reversed as $index => $id) {
            $row = $this->db->table('site_stats')->where('id', $id)->get()->getRowArray();
            $this->assertSame((string) ($index + 1), (string) $row['display_order']);
        }
    }

    public function testOrderableIndexExposesBulkFormAttributeAndSortableBody(): void
    {
        $this->actingAs($this->superAdminUser());

        $result = $this->get('/admin/programmes');

        $result->assertOK();
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('id="adminBulkForm"', $body);
        $this->assertStringContainsString('form="adminBulkForm"', $body);
        $this->assertStringContainsString('data-sortable', $body);
        $this->assertStringContainsString('data-sortable-url', $body);
        $this->assertStringContainsString('data-order-cell', $body);
        $this->assertStringContainsString('Glissez les lignes', $body);
    }

    public function testSiteStatsIndexIsDragOrderable(): void
    {
        $this->actingAs($this->superAdminUser());

        $result = $this->get('/admin/site-stats');
        $result->assertOK();
        $body = html_entity_decode((string) $result->response()->getBody(), ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString('data-sortable', $body);
        $this->assertStringContainsString('admin/site-stats/reorder', $body);
        $this->assertStringContainsString('data-order-cell', $body);
    }

    public function testSettingsOverviewRequiresPermission(): void
    {
        $this->actingAs($this->directPermissionUser(
            'ergo-limited@example.test',
            'ergolimited',
            ['admin.access'],
        ));

        $this->get('/admin/settings/global')->assertRedirect();
    }

    public function testEditorDoesNotSeeAdministrationZone(): void
    {
        $this->actingAs($this->groupedUser('ergo-editor@example.test', 'ergoeditor', 'editor', 'editor'));

        $result = $this->get('/admin');
        $result->assertOK();
        $result->assertSee('Accueil');
        $result->assertSee('Pages du site');
        $result->assertDontSee('Messages de contact');
        $result->assertDontSee('Coordonnées &amp; identité');
        $result->assertDontSee('Gérer les utilisateurs');
        $result->assertDontSee('Pages institutionnelles');
        $result->assertDontSee('Blocs de page');
    }

    public function testFacultyAdminCanOpenSettingsAndSeesAdministration(): void
    {
        $this->actingAs($this->groupedUser('ergo-admin@example.test', 'ergofacadmin', 'admin', 'site_admin'));

        $nav = $this->get('/admin');
        $nav->assertOK();
        $nav->assertSee('Administration');
        $nav->assertSee('Coordonnées &amp; identité');

        $settings = $this->get('/admin/settings/global');
        $settings->assertOK();
        $settings->assertSee('Coordonnées &amp; identité');
    }

    private function groupedUser(string $email, string $username, string $group, string $siteRole): User
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
            'password' => 'MotDePasseErgo!2026',
            'active'   => 1,
        ]);
        $users->save($user);

        /** @var User $created */
        $created = $users->findById($users->getInsertID());
        $created->removeGroup('editor', 'admin', 'superadmin');
        $created->addGroup($group);
        $created->activate();
        service('siteResolver')->syncUserSites((int) $created->id, [1], $siteRole);

        return $created;
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

    private function superAdminUser(string $email = 'ergo-super@example.test', string $username = 'ergosuper'): User
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
            'password' => 'MotDePasseErgo!2026',
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
            'password' => 'MotDePasseErgo!2026',
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
