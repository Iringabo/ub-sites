<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
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

        $result = $this->get('/admin');

        $result->assertOK();
        $result->assertSee('Mettre votre site en ligne');
        $result->assertSee('Personnaliser les textes de l’accueil');
        $result->assertSee('Publier une première actualité');
        $result->assertSee('0 / 6 étapes');
    }

    public function testChecklistProgressesWhenContentIsPersonalized(): void
    {
        $this->actingAs($this->superAdmin());

        $this->db->table('home_content')->where('site_id', 1)->update([
            'hero_title' => 'Faculté de démonstration personnalisée',
            'about_body' => 'Présentation réelle de la faculté.',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('settings')
            ->where('site_id', 1)
            ->where('key', 'contact.email')
            ->update(['value' => 'contact@faculte.test', 'updated_at' => date('Y-m-d H:i:s')]);

        $result = $this->get('/admin');

        $result->assertOK();
        $result->assertSee('2 / 6 étapes');
    }
}
