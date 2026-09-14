<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * La superadministration édite le contenu du site sélectionné.
 * Elle ne crée plus de faculté depuis l’interface.
 *
 * @internal
 */
final class CentralAdminLockdownTest extends CIUnitTestCase
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
        $existing = $users->where('username', 'lockdown-super')->first();
        if ($existing instanceof User) {
            return $existing;
        }

        $users->save(new User([
            'username' => 'lockdown-super',
            'email'    => 'lockdown-super@example.test',
            'password' => 'MotDePasseLockdown!2026',
            'active'   => 1,
        ]));

        $user = $users->findById((int) $users->getInsertID());
        $user->addGroup('superadmin');
        $user->activate();

        return $user;
    }

    public function testCentralInstanceShowsPlatformAndFacultyZones(): void
    {
        $this->actingAs($this->superAdmin());

        $central = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin');

        $central->assertOK();
        $central->assertSee('Superadministration');
        $central->assertSee('Facultés');
        $central->assertSee('Contenu et communication');
        $central->assertSee('Textes de l’accueil');
        $central->assertDontSee('Créer une faculté');
    }

    public function testContentModulesAreEditableOnCentralInstance(): void
    {
        $this->actingAs($this->superAdmin());

        $programmes = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin/programmes');
        $programmes->assertOK();

        $sites = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin/sites');
        $sites->assertOK();
    }

    public function testNewSiteScreenIsForbidden(): void
    {
        $this->actingAs($this->superAdmin());

        $result = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin/sites/new');
        $result->assertRedirect();
        $result->assertSessionHas('error');
    }

    public function testTemplateSiteIsHiddenFromCentralDashboard(): void
    {
        $this->db->table('sites')->insert([
            'identifier'     => 'template',
            'name'           => 'Squelette interne TEMPLATEUNIQUE',
            'slug'           => 'template',
            'hostnames'      => json_encode(['template.test']),
            'status'         => 'active',
            'default_locale' => 'fr',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->actingAs($this->superAdmin());
        $page = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin');
        $page->assertOK();
        $page->assertDontSee('TEMPLATEUNIQUE');
    }
}
