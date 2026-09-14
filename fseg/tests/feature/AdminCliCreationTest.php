<?php

use App\Commands\CreateFacultyAdmin;
use App\Commands\CreateSuperAdmin;
use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class AdminCliCreationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('PLATFORM_ADMIN_PASSWORD=MotDePasseCliAdmin!2026');
        $_ENV['PLATFORM_ADMIN_PASSWORD'] = 'MotDePasseCliAdmin!2026';
    }

    public function testCreateSuperAdminRefusedWhenNotCentral(): void
    {
        putenv('app.centralAdminMode=false');
        $_ENV['app.centralAdminMode'] = 'false';

        $command = new CreateSuperAdmin(service('logger'), service('commands'));
        $result  = $command->run([]);

        $this->assertSame(EXIT_ERROR, $result);
    }

    public function testCreateFacultyAdminRefusedWhenCentral(): void
    {
        putenv('app.centralAdminMode=true');
        $_ENV['app.centralAdminMode'] = 'true';

        $command = new CreateFacultyAdmin(service('logger'), service('commands'));
        $result  = $command->run([
            'email'        => 'should-fail@example.test',
            'username'     => 'shouldfail',
            'password-env' => 'PLATFORM_ADMIN_PASSWORD',
        ]);

        // Options via CLI::getOption need spark argv; call with env gate only.
        // When central, run() returns EXIT_ERROR before prompts.
        $this->assertSame(EXIT_ERROR, $result);
    }

    public function testCreateFacultyAdminCreatesSiteScopedAdmin(): void
    {
        putenv('app.centralAdminMode=false');
        $_ENV['app.centralAdminMode'] = 'false';
        putenv('app.siteSlug=fseg');
        $_ENV['app.siteSlug'] = 'fseg';

        $email    = 'faculty-cli-' . bin2hex(random_bytes(3)) . '@example.test';
        $username = 'faccli' . substr(bin2hex(random_bytes(2)), 0, 4);

        $command = new CreateFacultyAdmin(service('logger'), service('commands'));
        $result  = $command->run([
            'email'        => $email,
            'username'     => $username,
            'password-env' => 'PLATFORM_ADMIN_PASSWORD',
        ]);

        $this->assertSame(EXIT_SUCCESS, $result, 'faculty admin CLI should succeed on faculty instance');

        $identity = $this->db->table('auth_identities')
            ->where('type', 'email_password')
            ->where('secret', $email)
            ->get()
            ->getRowArray();
        $this->assertIsArray($identity);

        $userId = (int) $identity['user_id'];
        $this->assertSame(
            1,
            $this->db->table('auth_groups_users')
                ->where('user_id', $userId)
                ->where('group', 'admin')
                ->countAllResults(),
        );
        $this->assertSame(
            0,
            $this->db->table('auth_groups_users')
                ->where('user_id', $userId)
                ->where('group', 'superadmin')
                ->countAllResults(),
        );

        $siteId = (int) $this->db->table('sites')->where('slug', 'fseg')->get()->getRowArray()['id'];
        $assignment = $this->db->table('user_sites')
            ->where('user_id', $userId)
            ->where('site_id', $siteId)
            ->get()
            ->getRowArray();
        $this->assertIsArray($assignment);
        $this->assertSame('site_admin', $assignment['role']);
    }
}
