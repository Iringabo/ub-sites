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

    public function testFacultyAdminCanCreateAdminOnOwnSite(): void
    {
        $actor = $this->facultyAdminUser('faculty-admin-actor@example.test', 'facultyadminactor');
        $this->actingAs($actor);

        $form = $this->get('/admin/users/new');
        $form->assertOK();
        $form->assertSee('Administrateur');
        $form->assertSee('Éditeur');
        $form->assertDontSee('Superadministrateur');

        $create = $this->post('/admin/users', $this->withCsrf([
            'username'   => 'nouveauadminfac',
            'email'      => 'nouveau-admin-fac@example.test',
            'password'   => 'MotDePassePhase5!2026',
            'confirm'    => 'MotDePassePhase5!2026',
            'active'     => '1',
            'groups'     => ['admin'],
            'site_roles' => [1 => 'site_admin'],
        ]));

        $create->assertRedirect();
        $created = $this->userByEmail('nouveau-admin-fac@example.test');
        $this->assertInstanceOf(User::class, $created);
        $this->assertSame(['admin'], $created->getGroups());
        $this->assertSame(1, $this->db->table('user_sites')
            ->where('user_id', $created->id)
            ->where('site_id', 1)
            ->where('role', 'site_admin')
            ->countAllResults());
        $this->assertSame(0, $this->db->table('auth_groups_users')
            ->where('user_id', $created->id)
            ->where('group', 'superadmin')
            ->countAllResults());
    }

    public function testFacultyAdminSaveKeepsAssignmentsOnOtherSites(): void
    {
        $secondSiteId = $this->insertSecondarySite();
        $actor = $this->facultyAdminUser('faculty-merge-actor@example.test', 'facultymergeactor');
        $target = $this->facultyAdminUser('two-site-editor@example.test', 'twositeeditor', 'editor', 'editor');
        service('siteResolver')->upsertUserSiteRole((int) $target->id, $secondSiteId, 'editor');

        $this->assertSame(2, $this->db->table('user_sites')->where('user_id', $target->id)->countAllResults());

        $this->actingAs($actor);
        $this->post('/admin/users/' . $target->id, $this->withCsrf([
            'username'   => 'twositeeditor',
            'email'      => 'two-site-editor@example.test',
            'active'     => '1',
            'groups'     => ['editor'],
            'site_roles' => [1 => 'editor'],
        ]))->assertRedirect();

        $this->assertSame(1, $this->db->table('user_sites')
            ->where('user_id', $target->id)
            ->where('site_id', 1)
            ->where('role', 'editor')
            ->countAllResults());
        $this->assertSame(1, $this->db->table('user_sites')
            ->where('user_id', $target->id)
            ->where('site_id', $secondSiteId)
            ->where('role', 'editor')
            ->countAllResults());
    }

    public function testEditorCannotOpenUsersModule(): void
    {
        $this->actingAs($this->facultyAdminUser('editor-users-guard@example.test', 'editorusersguard', 'editor', 'editor'));

        $this->get('/admin/users')->assertRedirectTo(rtrim(base_url(), '/'));
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

    public function testSuperAdminCanDeleteUserAndCleansAssociations(): void
    {
        $this->superAdminUser('delete-backup@example.test', 'deletebackup');
        $actor = $this->superAdminUser('delete-actor@example.test', 'deleteactor');
        $target = $this->directPermissionUser('delete-target@example.test', 'deletetarget', ['admin.access']);

        $this->assertSame(1, $this->db->table('auth_identities')->where('user_id', $target->id)->countAllResults());
        $this->assertSame(1, $this->db->table('user_sites')->where('user_id', $target->id)->countAllResults());

        $this->actingAs($actor);

        $response = $this->post('/admin/users/' . $target->id . '/delete', $this->withCsrf());
        $response->assertRedirect();
        $response->assertSessionHas('message');

        $this->assertNull($this->userByEmail('delete-target@example.test'));

        $row = $this->db->table('users')->where('id', $target->id)->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertNotNull($row['deleted_at']);

        $this->assertSame(0, $this->db->table('auth_groups_users')->where('user_id', $target->id)->countAllResults());
        $this->assertSame(0, $this->db->table('auth_permissions_users')->where('user_id', $target->id)->countAllResults());
        $this->assertSame(0, $this->db->table('auth_identities')->where('user_id', $target->id)->countAllResults());
        $this->assertSame(0, $this->db->table('user_sites')->where('user_id', $target->id)->countAllResults());
    }

    public function testNonSuperAdminCannotDeleteUser(): void
    {
        $this->superAdminUser('delete-guard->super@example.test', 'deleteguardsuper');
        $actor = $this->managerUser('delete-guard-manager@example.test', 'deleteguardmanager');
        $target = $this->directPermissionUser('delete-guard-target@example.test', 'deleteguardtarget', ['admin.access']);

        $this->actingAs($actor);

        $response = $this->post('/admin/users/' . $target->id . '/delete', $this->withCsrf());
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertNotNull($this->userByEmail('delete-guard-target@example.test'));

        $row = $this->db->table('users')->where('id', $target->id)->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertNull($row['deleted_at']);
    }

    public function testSuperAdminCannotDeleteOwnAccount(): void
    {
        $this->superAdminUser('delete-self-backup@example.test', 'deleteselfbackup');
        $actor = $this->superAdminUser('delete-self-actor@example.test', 'deleteselfactor');
        $this->actingAs($actor);

        $response = $this->post('/admin/users/' . $actor->id . '/delete', $this->withCsrf());
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $row = $this->db->table('users')->where('id', $actor->id)->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertNull($row['deleted_at']);
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

    private function facultyAdminUser(
        string $email,
        string $username,
        string $group = 'admin',
        string $siteRole = 'site_admin',
    ): User {
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
        $created->addGroup($group);
        $created->activate();
        service('siteResolver')->syncUserSites((int) $created->id, [1], $siteRole);

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

    private function insertSecondarySite(): int
    {
        $existing = $this->db->table('sites')->where('slug', 'droit-users')->get()->getRowArray();
        if (is_array($existing)) {
            return (int) $existing['id'];
        }

        $siteId = model(SiteModel::class, false)->insert([
            'identifier'      => 'droit_users',
            'name'            => 'Faculté de Droit (users)',
            'slug'            => 'droit-users',
            'hostnames'       => ['droit-users.test'],
            'status'          => 'active',
            'default_locale'  => 'fr',
            'logo'            => 'assets/images/logo-placeholder.png',
            'primary_color'   => '#0D9B49',
            'secondary_color' => '#0B6F38',
            'contact_email'   => 'droit-users@example.test',
            'phone'           => '+257 22 22 00 00',
            'address'         => 'Bujumbura',
        ], true);

        $this->assertNotFalse($siteId);

        return (int) $siteId;
    }

    private function withCsrf(array $data = []): array
    {
        return array_merge($data, [csrf_token() => csrf_hash()]);
    }
}
