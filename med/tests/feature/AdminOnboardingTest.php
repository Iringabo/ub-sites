<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Régression : le tableau de bord d'une faculté fraîchement provisionnée
 * affiche une liste de mise en route (contenus « à remplacer » détectés),
 * qui progresse lorsque les contenus sont personnalisés.
 *
 * @internal
 */
final class AdminOnboardingTest extends CIUnitTestCase
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
        try {
            auth('session')->getAuthenticator()->logout();
        } catch (Throwable) {
        }
        $_SESSION = [];
        $this->withSession([]);
    }

    private function superAdmin(): User
    {
        $users = model(UserModel::class);
        $existing = $users->where('username', 'onboarding-super')->first();
        if ($existing instanceof User) {
            return $existing;
        }

        $users->save(new User([
            'username' => 'onboarding-super',
            'email'    => 'onboarding-super@example.test',
            'password' => 'MotDePasseOnboarding!2026',
            'active'   => 1,
        ]));

        $user = $users->findById((int) $users->getInsertID());
        $user->addGroup('superadmin');
        $user->activate();

        return $user;
    }

    public function testFreshSiteShowsTheOnboardingChecklist(): void
    {
        $this->actingAs($this->superAdmin());

        $onboarding = service('adminDashboardService')->onboarding();
        $result = $this->get('/admin');

        $result->assertOK();
        $result->assertSee('Mettre votre site en ligne');
        $result->assertSee('Personnaliser les textes de l’accueil');
        $result->assertSee('Publier une première actualité');
        $this->assertGreaterThan($onboarding['done'], $onboarding['total']);
        $result->assertSee($onboarding['done'] . ' / ' . $onboarding['total'] . ' étapes');
    }

    public function testChecklistProgressesWhenContentIsPersonalized(): void
    {
        $this->actingAs($this->superAdmin());
        $before = service('adminDashboardService')->onboarding()['done'];

        $this->db->table('home_content')->where('site_id', 1)->update([
            'hero_title' => 'Faculté de démonstration personnalisée',
            'about_body' => 'Présentation réelle de la faculté.',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('settings')
            ->where('site_id', 1)
            ->where('key', 'contact.email')
            ->update(['value' => 'contact@faculte.test', 'updated_at' => date('Y-m-d H:i:s')]);

        service('settingsService')->reset();
        $after = service('adminDashboardService')->onboarding();

        $result = $this->get('/admin');

        $result->assertOK();
        $this->assertGreaterThan($before, $after['done']);
        $result->assertSee($after['done'] . ' / ' . $after['total'] . ' étapes');
    }
}
