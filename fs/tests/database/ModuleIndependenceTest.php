<?php

use App\Database\Seeds\TemplateStarterSeeder;
use App\Models\LaboratoryModel;
use App\Models\ProgrammeModel;
use App\Models\ResearchProjectModel;
use App\Models\StaffModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class ModuleIndependenceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = null;
    protected $basePath    = APPPATH . 'Database';
    protected $seed        = TemplateStarterSeeder::class;
    protected $migrateOnce = true;
    protected $seedOnce    = true;

    public function testForbiddenDependencyColumnsAreAbsent(): void
    {
        foreach (['laboratory_id', 'lab_id'] as $column) {
            $this->assertFalse($this->db->fieldExists($column, 'research_projects'), 'Colonne interdite : research_projects.' . $column);
        }

        foreach (['staff_id', 'coordinator_id', 'responsible_staff_id'] as $column) {
            $this->assertFalse($this->db->fieldExists($column, 'programmes'), 'Colonne interdite : programmes.' . $column);
        }
    }

    public function testResearchProjectCanBeCreatedWithoutLaboratory(): void
    {
        $projects = model(ResearchProjectModel::class);
        $id = $projects->insert([
            'code'          => 'TEST-INDEPENDANT-' . bin2hex(random_bytes(3)),
            'title'         => 'Projet indépendant sans laboratoire',
            'description'   => 'Projet de test créé sans rattachement à un laboratoire.',
            'funder'        => 'FSEG',
            'period_start'  => 2026,
            'period_end'    => 2027,
            'icon'          => 'bi-diagram-3',
            'display_order' => 99,
            'is_published'  => 1,
        ], true);

        $this->assertIsInt($id);
    }

    public function testProgrammeCanBeCreatedWithoutStaffMember(): void
    {
        $programmes = model(ProgrammeModel::class);
        $id = $programmes->insert([
            'level'                => 'master',
            'title'                => 'Programme indépendant sans personnel',
            'slug'                 => 'programme-independant-sans-personnel-' . bin2hex(random_bytes(3)),
            'duration'             => '2 ans',
            'summary'              => 'Résumé du programme de test.',
            'description'          => 'Programme de test créé sans coordinateur ni responsable personnel.',
            'admission_conditions' => 'Conditions de test.',
            'career_outcomes'      => json_encode(['Débouché de test'], JSON_UNESCAPED_UNICODE),
            'display_order'        => 99,
            'featured_on_home'     => 0,
            'home_order'           => null,
            'is_published'         => 1,
        ], true);

        $this->assertIsInt($id);
    }

    public function testDeletingLaboratoryDoesNotDeleteResearchProjects(): void
    {
        $projectId = $this->insertIndependentProject();
        $before = $this->db->table('research_projects')->where('id', $projectId)->countAllResults();
        $laboratory = $this->db->table('laboratories')->select('id')->orderBy('id', 'ASC')->get()->getRowArray();

        $this->assertSame(1, $before);
        $this->assertIsArray($laboratory);
        $this->assertTrue(model(LaboratoryModel::class)->delete((int) $laboratory['id']));

        $after = $this->db->table('research_projects')->where('id', $projectId)->countAllResults();
        $this->assertSame(1, $after);
    }

    public function testDeletingStaffMemberDoesNotDeleteProgrammes(): void
    {
        $programmeId = $this->insertIndependentProgramme();
        $before = $this->db->table('programmes')->where('id', $programmeId)->countAllResults();
        $staff = $this->db->table('staff')->select('id')->orderBy('id', 'ASC')->get()->getRowArray();

        $this->assertSame(1, $before);
        $this->assertIsArray($staff);
        $this->assertTrue(model(StaffModel::class)->delete((int) $staff['id']));

        $after = $this->db->table('programmes')->where('id', $programmeId)->countAllResults();
        $this->assertSame(1, $after);
    }

    public function testValidationRulesDoNotRequireLaboratoryOrStaffDependencies(): void
    {
        $projectRules = model(ResearchProjectModel::class)->getValidationRules();
        $programmeRules = model(ProgrammeModel::class)->getValidationRules();

        foreach (['laboratory_id', 'lab_id'] as $field) {
            $this->assertArrayNotHasKey($field, $projectRules);
        }

        foreach (['staff_id', 'coordinator_id', 'responsible_staff_id'] as $field) {
            $this->assertArrayNotHasKey($field, $programmeRules);
        }
    }

    public function testAdminFormsDoNotRequireForbiddenDependencies(): void
    {
        $adminViews = $this->adminViewsContent();

        foreach (['laboratory_id', 'lab_id', 'staff_id', 'coordinator_id', 'responsible_staff_id'] as $field) {
            $this->assertStringNotContainsString('name="' . $field . '"', $adminViews);
            $this->assertStringNotContainsString("name='" . $field . "'", $adminViews);
            $this->assertStringNotContainsString($field . '" required', $adminViews);
            $this->assertStringNotContainsString($field . "' required", $adminViews);
        }
    }

    private function insertIndependentProject(): int
    {
        $id = model(ResearchProjectModel::class)->insert([
            'code'          => 'DELETE-LAB-' . bin2hex(random_bytes(3)),
            'title'         => 'Projet conservé après suppression laboratoire',
            'description'   => 'Projet de test sans dépendance laboratoire.',
            'display_order' => 100,
            'is_published'  => 1,
        ], true);

        $this->assertIsInt($id);

        return $id;
    }

    private function insertIndependentProgramme(): int
    {
        $id = model(ProgrammeModel::class)->insert([
            'level'                => 'licence',
            'title'                => 'Programme conservé après suppression personnel',
            'slug'                 => 'programme-conserve-apres-suppression-personnel-' . bin2hex(random_bytes(3)),
            'duration'             => '3 ans',
            'summary'              => 'Résumé du programme.',
            'description'          => 'Programme de test sans dépendance personnel.',
            'admission_conditions' => 'Conditions de test.',
            'career_outcomes'      => json_encode(['Débouché'], JSON_UNESCAPED_UNICODE),
            'display_order'        => 100,
            'featured_on_home'     => 0,
            'home_order'           => null,
            'is_published'         => 1,
        ], true);

        $this->assertIsInt($id);

        return $id;
    }

    private function adminViewsContent(): string
    {
        $directory = APPPATH . 'Views/admin';

        if (! is_dir($directory)) {
            return '';
        }

        $content = '';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content .= file_get_contents($file->getPathname()) ?: '';
        }

        return $content;
    }
}
