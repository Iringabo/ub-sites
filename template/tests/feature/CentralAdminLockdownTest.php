<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Régression : la superadministration pilote la plateforme (facultés,
 * comptes) mais n'expose plus l'édition du contenu éditorial, qui appartient
 * au dossier de chaque faculté.
 *
 * @internal
 */
final class CentralAdminLockdownTest extends CIUnitTestCase
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

    public function testCentralInstanceShowsPlatformNavigationOnly(): void
    {
        $this->actingAs($this->superAdmin());

        $central = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin');

        $central->assertOK();
        $central->assertSee('Superadministration');
        $central->assertSee('Facultés');
        $central->assertDontSee('Textes de l’accueil');
        $central->assertDontSee('Actualités & événements');
    }

    public function testContentModulesAreBlockedOnCentralInstance(): void
    {
        $this->actingAs($this->superAdmin());

        $blocked = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin/programmes');

        $blocked->assertRedirectTo(site_url('admin'));
        $blocked->assertSessionHas('error');

        $sites = $this->withHeaders(['Host' => 'admin.ub.local'])->get('/admin/sites');

        $sites->assertOK();
    }
}
