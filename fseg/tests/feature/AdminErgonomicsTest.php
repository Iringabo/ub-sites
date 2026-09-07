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
        foreach (['Institution', 'Coordonnées', 'Pied de page', 'Identité visuelle', 'Référencement (SEO)'] as $section) {
            $result->assertSee($section);
        }
        $result->assertSee('Titre SEO par défaut');
    }

    public function testSettingsUpdatePersistsAValue(): void
    {
        $this->actingAs($this->superAdminUser());

        $this->post('/admin/settings/global', $this->withCsrf([
            'setting_institution_faculty_name' => 'Faculté de test des coordonnées',
            'setting_institution_short_name'   => 'FT',
            'setting_institution_university'   => 'Université du Burundi',
            'setting_contact_address'          => 'Bujumbura',
            'setting_contact_phone'            => '+257 00 00 00 00',
            'setting_contact_email'            => 'contact@example.test',
            'setting_contact_hours'            => 'Lun-Ven 8h-17h',
            'setting_footer_text'              => 'Pied de page de test',
            'setting_footer_copyright'         => '© 2026 Faculté de test',
            'setting_seo_default_title'        => 'Titre SEO de test',
            'setting_seo_default_description'  => 'Description SEO de test.',
            'setting_seo_theme_color'          => '#0D9B49',
            'setting_home_hero_overlay_opacity'=> '0.80',
        ]))->assertRedirect();

        $row = $this->db->table('settings')
            ->where('key', 'institution.faculty_name')
            ->get()
            ->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame('Faculté de test des coordonnées', $row['value']);
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
            $this->db->table('home_highlights')->select('id')->orderBy('id', 'ASC')->limit(2)->get()->getResultArray(),
            'id',
        ));
        $this->assertNotEmpty($ids);

        $this->post('/admin/home-highlights/bulk', $this->withCsrf([
            'bulk_action' => 'delete',
            'ids'         => $ids,
        ]))->assertRedirect();

        foreach ($ids as $id) {
            $row = $this->db->table('home_highlights')->where('id', $id)->get()->getRowArray();
            $this->assertNotEmpty($row['deleted_at']);
        }
    }

    public function testDuplicateCreatesUnpublishedCopyWithUniqueSlug(): void
    {
        $this->actingAs($this->superAdminUser());

        $ids = array_map('intval', array_column(
            $this->db->table('programmes')->select('id')->orderBy('id', 'ASC')->limit(1)->get()->getResultArray(),
            'id',
        ));
        $this->assertNotEmpty($ids);
        $sourceId = $ids[0];
        $source = $this->db->table('programmes')->where('id', $sourceId)->get()->getRowArray();

        $this->post('/admin/programmes/' . $sourceId . '/duplicate', $this->withCsrf())->assertRedirect();

        $copies = $this->db->table('programmes')
            ->where('title', $source['title'])
            ->where('id !=', $sourceId)
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

        $rows = $this->db->table('home_highlights')->select('id')->orderBy('display_order', 'ASC')->limit(3)->get()->getResultArray();
        $ids = array_map('intval', array_column($rows, 'id'));
        $this->assertCount(3, $ids);

        $reversed = array_reverse($ids);
        $result = $this->post('/admin/home-highlights/reorder', $this->withCsrf(['ids' => $reversed]));
        $result->assertJSONExact(['ok' => true]);

        foreach ($reversed as $index => $id) {
            $row = $this->db->table('home_highlights')->where('id', $id)->get()->getRowArray();
            $this->assertSame((string) ($index + 1), (string) $row['display_order']);
        }
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
