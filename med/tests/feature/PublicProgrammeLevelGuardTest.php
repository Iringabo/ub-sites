<?php

use App\Database\Seeds\TemplateStarterSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Régressions de l'audit : robustesse de la page publique des formations
 * face à des données hors référentiel (niveau inconnu).
 *
 * @internal
 */
final class PublicProgrammeLevelGuardTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    public function testFormationsPageIgnoresUnknownLevelsInsteadOfFailing(): void
    {
        $this->db->table('programmes')->insert([
            'site_id'              => 1,
            'level'                => 'certificat-inconnu',
            'title'                => 'Programme hors référentiel',
            'slug'                 => 'hors-referentiel',
            'duration'             => '1 an',
            'summary'              => 'Résumé.',
            'description'          => 'Description.',
            'admission_conditions' => 'Aucune.',
            'career_outcomes'      => json_encode(['Débouché'], JSON_UNESCAPED_UNICODE),
            'display_order'        => 99,
            'featured_on_home'     => 0,
            'home_order'           => null,
            'is_published'         => 1,
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        $result = $this->get('/formations');

        $result->assertOK();
        $result->assertDontSee('Programme hors référentiel');
        $result->assertSee('Licence exemple à modifier');
    }
}
