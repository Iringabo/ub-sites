<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Les administrateurs facultaires se créent depuis Comptes, pas depuis
 * un assistant « nouveau site ».
 *
 * @internal
 */
final class SiteCreationFirstAdminTest extends CIUnitTestCase
{
    use AuthenticationTesting;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    private function superAdmin(): User
    {
        $users = model(UserModel::class);
        $existing = $users->where('username', 'firstadmin-super')->first();
        if ($existing instanceof User) {
            return $existing;
        }

        $users->save(new User([
            'username' => 'firstadmin-super',
            'email'    => 'firstadmin-super@example.test',
            'password' => 'MotDePasseFirstAdmin!2026',
            'active'   => 1,
        ]));

        $user = $users->findById((int) $users->getInsertID());
        $user->addGroup('superadmin');
        $user->activate();

        return $user;
    }

    public function testUiCannotCreateFacultyOrFirstAdminViaSites(): void
    {
        $this->actingAs($this->superAdmin());
        $before = (int) $this->db->table('sites')->countAllResults();

        $response = $this->post('/admin/sites', $this->withCsrf([
            'identifier'           => 'faca1',
            'name'                 => 'Faculté de Test a1',
            'slug'                 => 'fac-a1',
            'hostnames'            => 'fac-a1.test',
            'status'               => 'active',
            'first_admin_email'    => 'admin-fac-a1@example.test',
            'first_admin_username' => 'adminfaca1',
            'first_admin_password' => 'MotDePasseAdminA1!2026',
            'first_admin_confirm'  => 'MotDePasseAdminA1!2026',
        ]));

        $response->assertRedirect();
        $this->assertSame($before, (int) $this->db->table('sites')->countAllResults());
        $this->assertSame(0, $this->db->table('users')->where('username', 'adminfaca1')->countAllResults());
    }

    public function testSuperAdminCreatesFacultyAdminFromUsersModule(): void
    {
        $this->actingAs($this->superAdmin());
        $siteId = (int) $this->db->table('sites')->orderBy('id', 'ASC')->get()->getRowArray()['id'];

        $response = $this->post('/admin/users', $this->withCsrf([
            'username'    => 'adminfaca1',
            'email'       => 'admin-fac-a1@example.test',
            'password'    => 'MotDePasseAdminA1!2026',
            'confirm'     => 'MotDePasseAdminA1!2026',
            'active'      => '1',
            'groups'      => ['admin'],
            'permissions' => [],
            'site_ids'    => [(string) $siteId],
            'site_roles'  => [$siteId => 'site_admin'],
        ]));

        $response->assertRedirect();

        $user = $this->db->table('users')->where('username', 'adminfaca1')->get()->getRowArray();
        $this->assertIsArray($user);
        $this->assertSame(
            1,
            $this->db->table('auth_groups_users')
                ->where('user_id', $user['id'])
                ->where('group', 'admin')
                ->countAllResults(),
        );

        $assignment = $this->db->table('user_sites')
            ->where('user_id', $user['id'])
            ->where('site_id', $siteId)
            ->get()
            ->getRowArray();
        $this->assertIsArray($assignment);
        $this->assertSame('site_admin', $assignment['role']);
    }

    public function testSuperAdminCanCreateThenDeleteFacultyAdmin(): void
    {
        $this->assertSuperAdminCanCreateThenDeleteFacultyAdmin(
            'facadmindel',
            'facadmindel@example.test',
        );

        $this->setCentralAdminMode(true);

        try {
            service('siteResolver')->reset();
            $this->assertSuperAdminCanCreateThenDeleteFacultyAdmin(
                'facadmindelcentral',
                'facadmindelcentral@example.test',
            );
        } finally {
            $this->setCentralAdminMode(false);
            service('siteResolver')->reset();
        }
    }

    private function assertSuperAdminCanCreateThenDeleteFacultyAdmin(string $username, string $email): void
    {
        auth()->logout();
        $this->actingAs($this->superAdmin());
        $siteId = (int) $this->db->table('sites')->orderBy('id', 'ASC')->get()->getRowArray()['id'];

        $create = $this->post('/admin/users', $this->withCsrf([
            'username'    => $username,
            'email'       => $email,
            'password'    => 'MotDePasseAdminDel!2026',
            'confirm'     => 'MotDePasseAdminDel!2026',
            'active'      => '1',
            'groups'      => ['admin'],
            'permissions' => [],
            'site_ids'    => [(string) $siteId],
            'site_roles'  => [$siteId => 'site_admin'],
        ]));

        $create->assertRedirect();
        $create->assertSessionHas('message', 'L’utilisateur a été créé.');

        $user = $this->db->table('users')->where('username', $username)->get()->getRowArray();
        $this->assertIsArray($user);
        $userId = (int) $user['id'];

        $this->assertSame(
            1,
            $this->db->table('auth_groups_users')
                ->where('user_id', $userId)
                ->where('group', 'admin')
                ->countAllResults(),
        );
        $assignment = $this->db->table('user_sites')
            ->where('user_id', $userId)
            ->where('site_id', $siteId)
            ->get()
            ->getRowArray();
        $this->assertIsArray($assignment);
        $this->assertSame('site_admin', $assignment['role']);

        $delete = $this->post('/admin/users/' . $userId . '/delete', $this->withCsrf());
        $delete->assertRedirect();
        $delete->assertSessionHas('message', 'Le compte a été supprimé définitivement.');

        $this->assertNull(auth()->getProvider()->findByCredentials(['email' => $email]));

        $row = $this->db->table('users')->where('id', $userId)->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertNotNull($row['deleted_at']);

        $this->assertSame(0, $this->db->table('auth_groups_users')->where('user_id', $userId)->countAllResults());
        $this->assertSame(0, $this->db->table('auth_identities')->where('user_id', $userId)->countAllResults());
        $this->assertSame(0, $this->db->table('user_sites')->where('user_id', $userId)->countAllResults());
    }

    private function setCentralAdminMode(bool $enabled): void
    {
        $value = $enabled ? 'true' : 'false';
        putenv('app.centralAdminMode=' . $value);
        $_ENV['app.centralAdminMode']    = $value;
        $_SERVER['app.centralAdminMode'] = $value;
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
