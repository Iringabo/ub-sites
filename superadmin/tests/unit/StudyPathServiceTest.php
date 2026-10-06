<?php

use App\Services\StudyPathService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * @internal
 */
final class StudyPathServiceTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::language()->setLocale('fr');
    }

    public function testYearLabelsUseRomanNumeralsInLicenceAndArabicInMaster(): void
    {
        $service = new StudyPathService();

        $this->assertSame('Bac I', $service->yearLabel('licence', 1));
        $this->assertSame('Bac III–IV', $service->yearLabel('licence', 3, 4));
        $this->assertSame('Master 1', $service->yearLabel('master', 1, 1));
        $this->assertSame('Master 2–4', $service->yearLabel('master', 2, 4));

        Services::language()->setLocale('en');
        $this->assertSame('Year 2', $service->yearLabel('licence', 2));
    }

    public function testNestedAndCrossDepartmentTrunksBuildOneTreePerDepartment(): void
    {
        [$stored, $departments] = $this->fsi();
        $paths = (new StudyPathService())->forDepartment(4, $stored, $stored, $departments);

        $this->assertSame(['licence'], array_keys($paths));
        $this->assertCount(1, $paths['licence']['trunks']);
        $this->assertCount(1, $paths['licence']['singles']);
        $this->assertSame('XX', $paths['licence']['singles'][0]['programme']->option_code);
        $this->assertNull($paths['licence']['singles'][0]['years']);

        $trunk = $paths['licence']['trunks'][0];
        $this->assertSame('Tronc commun TIC', $trunk['name']);
        $this->assertSame('Bac I', $trunk['years']);
        $this->assertSame([], $trunk['departments']);
        $this->assertSame(['Tronc commun GI-GL', 'Tronc commun SE-RT'], array_column($trunk['branches'], 'name'));
        $this->assertSame('Bac II', $trunk['branches'][0]['years']);
        $this->assertSame(['GI', 'GL'], array_map(static fn (array $item): string => $item['programme']->option_code, $trunk['branches'][0]['options']));
        $this->assertSame('Bac III–IV', $trunk['branches'][0]['options'][0]['years']);

        $civil = (new StudyPathService())->forDepartment(2, $stored, $stored, $departments)['licence']['trunks'][0];
        $this->assertSame('Tronc commun FSI', $civil['name']);
        $this->assertSame(['EM', 'AU'], array_map(static fn (object $department): string => $department->abbreviation, $civil['departments']));
        $this->assertSame('Tronc commun Génie civil', $civil['branches'][0]['name']);
        $this->assertSame([], $civil['options']);
    }

    public function testProgrammeStagesAndSummaries(): void
    {
        [$stored, $departments] = $this->fsi();
        $service = new StudyPathService();

        $stages = $service->forProgramme(7, $stored, $stored, $departments);
        $this->assertSame(['core', 'branch', 'option'], array_column($stages, 'kind'));
        $this->assertSame(['Bac I', 'Bac II', 'Bac III–IV'], array_column($stages, 'years'));
        $this->assertSame(['GL', 'RT', 'SE'], array_map(static fn (object $peer): string => $peer->option_code, $stages[0]['peers']));
        $this->assertSame(['GL'], array_map(static fn (object $peer): string => $peer->option_code, $stages[1]['peers']));
        $this->assertSame('GI', $stages[2]['name']);

        $gme = $service->forProgramme(1, $stored, $stored, $departments);
        $this->assertSame(['core', 'option'], array_column($gme, 'kind'));
        $this->assertSame('Bac II–IV', $gme[1]['years']);
        $this->assertSame(['GC', 'AU'], array_map(static fn (object $department): string => $department->abbreviation, $gme[0]['departments']));

        $this->assertSame([], $service->forProgramme(11, $stored, $stored, $departments), 'A trunk followed by one programme only is not shown.');

        $summaries = $service->summaries($stored, $stored);
        $this->assertSame(['core' => 'Tronc commun TIC', 'from' => 'Bac III'], $summaries[7]);
        $this->assertSame(['core' => 'Tronc commun FSI', 'from' => 'Bac II'], $summaries[1]);
        $this->assertArrayNotHasKey(11, $summaries);
    }

    /**
     * @return array{0: array<int, object>, 1: array<int, object>}
     */
    private function fsi(): array
    {
        $departments = [];
        foreach ([1 => 'EM', 2 => 'GC', 3 => 'AU', 4 => 'TIC'] as $id => $abbreviation) {
            $departments[$id] = (object) ['id' => $id, 'slug' => strtolower($abbreviation), 'name' => 'Département ' . $abbreviation, 'abbreviation' => $abbreviation];
        }

        $programme = static fn (int $id, int $department, string $code, string $core, ?string $branch = null): object => (object) [
            'id' => $id, 'department_id' => $department, 'level' => 'licence', 'title' => 'Licence ' . $code, 'slug' => strtolower($code),
            'option_code' => $code, 'duration' => '4 ans', 'credits' => 240,
            'common_core' => $core, 'common_core_years' => 1, 'branch_core' => $branch, 'branch_core_years' => $branch === null ? null : 1,
        ];

        $stored = [
            1  => $programme(1, 1, 'GME', 'Tronc commun FSI'),
            2  => $programme(2, 1, 'GEL', 'Tronc commun FSI'),
            3  => $programme(3, 2, 'BI', 'Tronc commun FSI', 'Tronc commun Génie civil'),
            4  => $programme(4, 2, 'GH', 'Tronc commun FSI', 'Tronc commun Génie civil'),
            5  => $programme(5, 3, 'AMU', 'Tronc commun FSI'),
            7  => $programme(7, 4, 'GI', 'Tronc commun TIC', 'Tronc commun GI-GL'),
            8  => $programme(8, 4, 'GL', 'Tronc commun TIC', 'Tronc commun GI-GL'),
            9  => $programme(9, 4, 'RT', 'Tronc commun TIC', 'Tronc commun SE-RT'),
            10 => $programme(10, 4, 'SE', 'Tronc commun TIC', 'Tronc commun SE-RT'),
            11 => $programme(11, 4, 'XX', 'Tronc commun isolé'),
        ];

        return [$stored, $departments];
    }
}
