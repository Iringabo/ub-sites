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
final class AdminUsersTest extends CIUnitTestCase
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
        auth('session')->getAuthenticator()->logout();
        $this->deleteTestUsers();
    }

    protected function tearDown(): void
    {
        auth('session')->getAuthenticator()->logout();
        $this->deleteTestUsers();
        parent::tearDown();
    }

    public function testUsersModuleRequiresAdminAccessAndUsersManagePermission(): void
    {
        $this->actingAs($this->directPermissionUser(
            'access-only@example.test',
            'accessonly',
            ['admin.access'],
        ));

        $this->get('/admin/users')->assertRedirectTo(rtrim(base_url(), '/'));
    }

    public function testSuperAdminCanCreateUserAssignGroupsAndResetPassword(): void
    {
        $this->actingAs($this->superAdminUser());

        $create = $this->post('/admin/users', $this->withCsrf([
            'username'    => 'phase5manager',
            'email'       => 'phase5-manager@example.test',
            'password'    => 'MotDePassePhase5!2026',
            'confirm'     => 'MotDePassePhase5!2026',
            'active'      => '1',
            'groups'      => ['editor'],
            'permissions' => ['users.manage'],
        ]));

        $create->assertRedirect();

        $created = $this->db->table('users')
            ->where('username', 'phase5manager')
            ->get()
            ->getRowArray();

        $this->assertIsArray($created);
        $this->assertSame('1', (string) $created['active']);

        $user = $this->userByEmail('phase5-manager@example.test');
        $this->assertInstanceOf(User::class, $user);

        $groups = $this->db->table('auth_groups_users')->where('user_id', $user->id)->get()->getResultArray();
        $permissions = $this->db->table('auth_permissions_users')->where('user_id', $user->id)->get()->getResultArray();

        $this->assertSame(['editor'], array_column($groups, 'group'));
        $this->assertSame(['users.manage'], array_column($permissions, 'permission'));

        $before = $this->userByEmail('phase5-manager@example.test');
        $this->assertInstanceOf(User::class, $before);
        $beforeHash = $before->getPasswordHash();

        $reset = $this->post('/admin/users/' . $user->id . '/password', $this->withCsrf([
            'password' => 'MotDePassePhase5!2027',
            'confirm'  => 'MotDePassePhase5!2027',
        ]));

        $reset->assertRedirect();

        $after = $this->userByEmail('phase5-manager@example.test');
        $this->assertInstanceOf(User::class, $after);
        $this->assertNotSame($beforeHash, $after->getPasswordHash());
    }

    public function testCannotDisableLastSuperAdmin(): void
    {
        $actor = $this->managerUser();
        $target = $this->superAdminUser('last-super@example.test', 'lastsuper');

        $this->actingAs($actor);

        $response = $this->post('/admin/users/' . $target->id . '/deactivate', $this->withCsrf());
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $reloaded = $this->userByEmail('last-super@example.test');
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertTrue((bool) $reloaded->active);
    }

    public function testUserManagerCannotAssignSuperAdminGroup(): void
    {
        $this->actingAs($this->managerUser('delegated-manager@example.test', 'delegatedmanager'));

        $form = $this->get('/admin/users/new');
        $form->assertOK();
        $form->assertDontSee('Superadministrateur');

        $create = $this->post('/admin/users', $this->withCsrf([
            'username'    => 'forbiddensuper',
            'email'       => 'forbidden-super@example.test',
            'password'    => 'MotDePassePhase5!2026',
            'confirm'     => 'MotDePassePhase5!2026',
            'active'      => '1',
            'groups'      => ['superadmin'],
            'permissions' => [],
        ]));

        $create->assertRedirect();
        $create->assertSessionHas('errors');
        $this->assertNull($this->userByEmail('forbidden-super@example.test'));
    }

    public function testUserManagerCannotModifyProtectedSuperAdminAccount(): void
    {
        $actor = $this->managerUser('protected-manager@example.test', 'protectedmanager');
        $target = $this->superAdminUser('protected-super@example.test', 'protectedsuper');
        $this->superAdminUser('backup-super@example.test', 'backupsuper');

        $beforeHash = $target->getPasswordHash();
        $this->actingAs($actor);

        $index = $this->get('/admin/users');
        $index->assertOK();
        $index->assertSee('Hors périmètre de modification');

        $edit = $this->get('/admin/users/' . $target->id . '/edit');
        $edit->assertRedirect();
        $edit->assertSessionHas('error');

        $update = $this->post('/admin/users/' . $target->id, $this->withCsrf([
            'username'    => 'protectedsuper',
            'email'       => 'protected-super@example.test',
            'active'      => '1',
            'groups'      => ['admin'],
            'permissions' => [],
        ]));

        $update->assertRedirect();
        $update->assertSessionHas('error');

        $deactivate = $this->post('/admin/users/' . $target->id . '/deactivate', $this->withCsrf());
        $deactivate->assertRedirect();
        $deactivate->assertSessionHas('error');

        $password = $this->get('/admin/users/' . $target->id . '/password');
        $password->assertRedirect();
        $password->assertSessionHas('error');

        $reset = $this->post('/admin/users/' . $target->id . '/password', $this->withCsrf([
            'password' => 'MotDePassePhase5!2028',
            'confirm'  => 'MotDePassePhase5!2028',
        ]));
        $reset->assertRedirect();
        $reset->assertSessionHas('error');

        $reloaded = $this->userByEmail('protected-super@example.test');
        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertTrue((bool) $reloaded->active);
        $this->assertSame($beforeHash, $reloaded->getPasswordHash());
        $this->assertSame(1, $this->db->table('auth_groups_users')
            ->where('user_id', $target->id)
            ->where('group', 'superadmin')
            ->countAllResults());
    }

    public function testSelfLockoutIsPreventedAndValidationErrorsStayVisible(): void
    {
        $actor = $this->superAdminUser('self-lock@example.test', 'selflock');
        $this->actingAs($actor);

        $update = $this->post('/admin/users/' . $actor->id, $this->withCsrf([
            'username'    => 'selflock',
            'email'       => 'self-lock@example.test',
            'active'      => '1',
            'groups'      => [],
            'permissions' => [],
        ]));

        $update->assertRedirect();
        $update->assertSessionHas('errors');

        // Preserve the flashed validation state so the redirected form can render it.
        $this->withSession($_SESSION);

        $page = $this->get('/admin/users/' . $actor->id . '/edit');
        $page->assertOK();
        $page->assertSee('Vous ne pouvez pas retirer votre propre accès à l’administration.');
    }

    public function testInvalidUserSubmissionDisplaysFieldErrors(): void
    {
        $this->actingAs($this->managerUser('validation-manager@example.test', 'validationmanager'));

        $result = $this->post('/admin/users', $this->withCsrf([
            'username'    => 'ab',
            'email'       => 'pas-un-email',
            'password'    => 'MotDePassePhase5!2026',
            'confirm'     => 'MotDePassePhase5!2027',
            'active'      => '1',
            'groups'      => ['editor'],
            'permissions' => ['users.manage'],
        ]));

        $result->assertRedirect();
        $result->assertSessionHas('errors');

        // Preserve the flashed validation state so the redirected form can render it.
        $this->withSession($_SESSION);

        $page = $this->get('/admin/users/new');
        $page->assertOK();
        $page->assertSee('is-invalid');
        $page->assertSee('Le nom d’utilisateur doit contenir au moins 3 caractères.');
        $page->assertSee('L’adresse électronique doit être valide.');
        $page->assertSee('Les deux mots de passe doivent correspondre.');
    }

    private function superAdminUser(string $email = 'users-super@example.test', string $username = 'userssuper'): User
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);

        $existing = $users->findByCredentials(['email' => $email]);
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

    private function managerUser(string $email = 'users-manager@example.test', string $username = 'usersmanager'): User
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);

        $existing = $users->findByCredentials(['email' => $email]);
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
        $created->addPermission('admin.access', 'users.manage');
        $created->activate();
        service('siteResolver')->syncUserSites((int) $created->id, [1], 'site_admin');

        return $created;
    }

    private function directPermissionUser(string $email, string $username, array $permissions): User
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);

        $existing = $users->findByCredentials(['email' => $email]);
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

    private function userByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = auth()->getProvider()->findByCredentials(['email' => $email]);

        return $user;
    }

    private function deleteTestUsers(): void
    {
        $this->db->disableForeignKeyChecks();

        foreach ([
            'auth_remember_tokens',
            'auth_groups_users',
            'auth_permissions_users',
            'auth_identities',
            'auth_logins',
            'auth_token_logins',
            'users',
        ] as $table) {
            $this->db->table($table)->truncate();
        }

        $this->db->enableForeignKeyChecks();
    }

    private function withCsrf(array $data = []): array
    {
        return array_merge($data, [csrf_token() => csrf_hash()]);
    }
}
