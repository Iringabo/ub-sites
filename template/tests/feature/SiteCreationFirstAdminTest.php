<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Régression : création d'une faculté avec son administrateur initial en
 * une seule étape depuis la superadministration.
 *
 * @internal
 */
final class SiteCreationFirstAdminTest extends CIUnitTestCase
{
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

    /**
     * @return array<string, mixed>
     */
    private function sitePayload(string $suffix): array
    {
        return [
            'identifier'       => 'fac' . $suffix,
            'name'             => 'Faculté de Test ' . $suffix,
            'slug'             => 'fac-' . $suffix,
            'hostnames'        => "fac-{$suffix}.test",
            'status'           => 'active',
            'default_locale'   => 'fr',
            'primary_color'    => '#0D9B49',
            'secondary_color'  => '#0B6F38',
            'theme'            => 'default',
            'theme_config'     => '{"layout":"classic"}',
            'menu_config'      => '{"items":["faculte","contact"]}',
            'enabled_sections' => "hero\ncontact_cta",
            'contact_email'    => "fac{$suffix}@example.test",
            'phone'            => '+257 00 00 00 00',
            'address'          => 'Campus de test',
        ];
    }

    public function testCreatesFacultyAndItsFirstAdminInOneStep(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->post('/admin/sites', $this->withCsrf(array_merge(
            $this->sitePayload('a1'),
            [
                'first_admin_email'    => 'admin-fac-a1@example.test',
                'first_admin_username' => 'adminfaca1',
                'first_admin_password' => 'MotDePasseAdminA1!2026',
                'first_admin_confirm'  => 'MotDePasseAdminA1!2026',
            ],
        )));

        $response->assertRedirect();
        $response->assertSessionHas('message');
        $this->assertStringContainsString('administrateur', session('message'));

        $site = $this->db->table('sites')->where('slug', 'fac-a1')->get()->getRowArray();
        $this->assertIsArray($site);

        $user = $this->db->table('users')->where('username', 'adminfaca1')->get()->getRowArray();
        $this->assertIsArray($user);
        $this->assertSame('1', (string) $user['active']);

        $this->assertSame(
            1,
            $this->db->table('auth_groups_users')
                ->where('user_id', $user['id'])
                ->where('group', 'admin')
                ->countAllResults(),
        );

        $assignment = $this->db->table('user_sites')
            ->where('user_id', $user['id'])
            ->where('site_id', $site['id'])
            ->get()
            ->getRowArray();

        $this->assertIsArray($assignment);
        $this->assertSame('site_admin', $assignment['role']);
    }

    public function testInvalidFirstAdminBlocksTheWholeCreation(): void
    {
        $this->actingAs($this->superAdmin());

        $before = (int) $this->db->table('sites')->countAllResults();

        $response = $this->post('/admin/sites', $this->withCsrf(array_merge(
            $this->sitePayload('b2'),
            [
                'first_admin_email'    => 'admin-fac-b2@example.test',
                'first_admin_username' => 'adminfacb2',
                'first_admin_password' => 'court',
                'first_admin_confirm'  => 'different',
            ],
        )));

        $response->assertRedirect();
        $response->assertSessionHas('errors');

        $this->assertSame($before, (int) $this->db->table('sites')->countAllResults());
        $this->assertSame(0, $this->db->table('users')->where('username', 'adminfacb2')->countAllResults());
    }

    public function testFacultyCreationWithoutFirstAdminStillWorks(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->post('/admin/sites', $this->withCsrf($this->sitePayload('c3')));

        $response->assertRedirect();
        $this->assertSame(
            1,
            $this->db->table('sites')->where('slug', 'fac-c3')->countAllResults(),
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function withCsrf(array $data): array
    {
        $security = service('security');
        $token    = $security->getTokenName();
        $hash     = $security->generateHash();

        $this->withSession(array_replace($_SESSION, [$token => $hash]));

        return array_replace([$token => $hash], $data);
    }
}
