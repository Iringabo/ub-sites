<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Régression : quitter l'administration (consulter le site public) détruit
 * la session — tout retour dans /admin exige une nouvelle connexion.
 *
 * @internal
 */
final class AdminSessionLifecycleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    private function facultyAdmin(): User
    {
        $users = model(UserModel::class);
        $users->save(new User([
            'username' => 'lifecycle-admin',
            'email'    => 'lifecycle-admin@example.test',
            'password' => 'MotDePasseLifecycle!2026',
            'active'   => 1,
        ]));

        $user = $users->findById((int) $users->getInsertID());
        $user->addGroup('admin');
        $user->activate();
        service('siteResolver')->syncUserSites((int) $user->id, [1], 'site_admin');

        return $user;
    }

    public function testLeavingAdminAreaToPublicSiteRequiresLoginAgain(): void
    {
        $this->actingAs($this->facultyAdmin());

        $this->get('/admin')->assertOK();

        // L'utilisateur quitte l'administration pour le site public.
        $this->get('/')->assertOK();

        // Tout retour dans l'administration exige une reconnexion.
        $this->get('/admin')->assertRedirectTo(site_url('login'));
    }

    public function testHealthzProbeDoesNotEndTheAdminSession(): void
    {
        $this->actingAs($this->facultyAdmin());

        $this->get('/admin')->assertOK();
        $this->get('/healthz')->assertStatus(204);
        $this->get('/admin')->assertOK();
    }

    public function testGuestBrowsingPublicSiteIsUnaffected(): void
    {
        $this->get('/')->assertOK();
        $this->get('/admin')->assertRedirectTo(site_url('login'));
    }
}
