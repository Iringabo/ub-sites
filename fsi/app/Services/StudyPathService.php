<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProgrammeModel;
use Throwable;

/**
 * How studies unfold: a first tronc commun (possibly shared with other
 * departments), an optional narrower trunk, then the option itself.
 * Trunks are matched on the stored (French) names; labels come from the
 * translated records.
 */
class StudyPathService
{
    /**
     * Published programmes of the active site, stored and translated, keyed by id.
     *
     * @return array{stored: array<int, object>, display: array<int, object>}
     */
    public function catalogue(): array
    {
        try {
            $rows = model(ProgrammeModel::class, false)
                ->forSite()
                ->where('is_published', 1)
                ->orderBy('display_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->findAll();
        } catch (Throwable) {
            $rows = [];
        }

        $stored = [];
        foreach ($rows as $row) {
            $stored[(int) $row->id] = $row;
        }

        $display = [];
        foreach (service('contentTranslationService')->records('programmes', array_values($stored)) as $record) {
            $display[(int) $record->id] = $record;
        }

        return ['stored' => $stored, 'display' => $display];
    }

    /**
     * Study paths of one department, per level. Each trunk lists the options
     * of this department that follow it and the other departments sharing it.
     *
     * @param array<int, object> $stored
     * @param array<int, object> $display
     * @param array<int, object> $departments published departments keyed by id
     *
     * @return array<string, array{trunks: list<array<string, mixed>>, singles: list<array{programme: object, years: ?string}>}>
     */
    public function forDepartment(int $departmentId, array $stored, array $display, array $departments): array
    {
        [$cores, $branches] = $this->groups($stored);
        $paths = [];

        foreach ($stored as $id => $programme) {
            if ((int) ($programme->department_id ?? 0) !== $departmentId || ! isset($display[$id])) {
                continue;
            }

            $level = (string) $programme->level;
            $paths[$level] ??= ['trunks' => [], 'singles' => []];
            $item = ['programme' => $display[$id], 'years' => $this->optionYears($programme, $cores, $branches)];
            $coreKey = $this->validKey(ProgrammeModel::coreKey($programme), $cores);

            if ($coreKey === null) {
                $paths[$level]['singles'][] = $item;

                continue;
            }

            if (! isset($paths[$level]['trunks'][$coreKey])) {
                $coreYears = ProgrammeModel::coreYears($programme);
                $paths[$level]['trunks'][$coreKey] = [
                    'name'        => (string) ($display[$id]->common_core ?? $programme->common_core),
                    'years'       => $this->yearLabel($level, 1, $coreYears),
                    'departments' => $this->otherDepartments($cores[$coreKey], $stored, $departments, $departmentId),
                    'memberCount' => count($cores[$coreKey]),
                    'branches'    => [],
                    'options'     => [],
                ];
            }

            $branchKey = $this->validKey(ProgrammeModel::branchKey($programme), $branches);
            if ($branchKey === null) {
                $paths[$level]['trunks'][$coreKey]['options'][] = $item;

                continue;
            }

            if (! isset($paths[$level]['trunks'][$coreKey]['branches'][$branchKey])) {
                $from = ProgrammeModel::coreYears($programme) + 1;
                $paths[$level]['trunks'][$coreKey]['branches'][$branchKey] = [
                    'name'        => (string) ($display[$id]->branch_core ?? $programme->branch_core),
                    'years'       => $this->yearLabel($level, $from, $from + ProgrammeModel::branchYears($programme) - 1),
                    'departments' => $this->otherDepartments($branches[$branchKey], $stored, $departments, $departmentId),
                    'options'     => [],
                ];
            }

            $paths[$level]['trunks'][$coreKey]['branches'][$branchKey]['options'][] = $item;
        }

        foreach ($paths as $level => $path) {
            $trunks = [];
            foreach ($path['trunks'] as $trunk) {
                $trunk['branches'] = array_values($trunk['branches']);
                $trunks[] = $trunk;
            }
            $paths[$level]['trunks'] = $trunks;
        }

        return $paths;
    }

    /**
     * Stages of one programme, from the first trunk to the option. Empty when
     * the programme shares no trunk.
     *
     * @param array<int, object> $stored
     * @param array<int, object> $display
     * @param array<int, object> $departments
     *
     * @return list<array{kind: string, name: string, years: ?string, peers: list<object>, departments: list<object>}>
     */
    public function forProgramme(int $programmeId, array $stored, array $display, array $departments): array
    {
        $programme = $stored[$programmeId] ?? null;
        if ($programme === null) {
            return [];
        }

        [$cores, $branches] = $this->groups($stored);
        $coreKey = $this->validKey(ProgrammeModel::coreKey($programme), $cores);
        if ($coreKey === null) {
            return [];
        }

        $level = (string) $programme->level;
        $departmentId = (int) ($programme->department_id ?? 0);
        $record = $display[$programmeId] ?? $programme;
        $coreYears = ProgrammeModel::coreYears($programme);
        $peers = fn (array $ids): array => array_values(array_filter(array_map(static fn (int $id): ?object => $display[$id] ?? null, array_diff($ids, [$programmeId]))));

        $stages = [[
            'kind'        => 'core',
            'name'        => (string) ($record->common_core ?? $programme->common_core),
            'years'       => $this->yearLabel($level, 1, $coreYears),
            'peers'       => $peers($cores[$coreKey]),
            'departments' => $this->otherDepartments($cores[$coreKey], $stored, $departments, $departmentId),
        ]];

        $branchKey = $this->validKey(ProgrammeModel::branchKey($programme), $branches);
        if ($branchKey !== null) {
            $stages[] = [
                'kind'        => 'branch',
                'name'        => (string) ($record->branch_core ?? $programme->branch_core),
                'years'       => $this->yearLabel($level, $coreYears + 1, $coreYears + ProgrammeModel::branchYears($programme)),
                'peers'       => $peers($branches[$branchKey]),
                'departments' => $this->otherDepartments($branches[$branchKey], $stored, $departments, $departmentId),
            ];
        }

        $stages[] = [
            'kind'        => 'option',
            'name'        => trim((string) ($record->option_code ?? '')) ?: (string) $record->title,
            'years'       => $this->optionYears($programme, $cores, $branches),
            'peers'       => [],
            'departments' => [],
        ];

        return $stages;
    }

    /**
     * Programme id => trunk name and the year the option starts, for list badges.
     *
     * @param array<int, object> $stored
     * @param array<int, object> $display
     *
     * @return array<int, array{core: string, from: string}>
     */
    public function summaries(array $stored, array $display): array
    {
        [$cores, $branches] = $this->groups($stored);
        $summaries = [];

        foreach ($stored as $id => $programme) {
            if ($this->validKey(ProgrammeModel::coreKey($programme), $cores) === null) {
                continue;
            }

            $level = (string) $programme->level;
            $summaries[$id] = [
                'core' => (string) (($display[$id] ?? $programme)->common_core ?? $programme->common_core),
                'from' => $this->yearLabel($level, $this->optionStart($programme, $cores, $branches)),
            ];
        }

        return $summaries;
    }

    /**
     * "Bac II", "Bac III–IV", "Master 1"... in the current locale.
     */
    public function yearLabel(string $level, int $from, ?int $to = null): string
    {
        $level = $level === 'master' ? 'master' : 'licence';
        $numerals = lang('Site.programmes.yearNumerals.' . $level);
        $format = static fn (int $year): string => $numerals === 'roman' ? self::roman($year) : (string) $year;

        if ($to === null || $to <= $from) {
            return lang('Site.programmes.yearLabel.' . $level, [$format($from)]);
        }

        return lang('Site.programmes.yearRange.' . $level, [$format($from), $format($to)]);
    }

    public static function roman(int $number): string
    {
        $map = ['X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $roman = '';
        foreach ($map as $symbol => $value) {
            while ($number >= $value) {
                $roman .= $symbol;
                $number -= $value;
            }
        }

        return $roman;
    }

    /**
     * Trunk key => member ids, kept only when at least two programmes share it.
     *
     * @param array<int, object> $stored
     *
     * @return array{0: array<string, list<int>>, 1: array<string, list<int>>}
     */
    private function groups(array $stored): array
    {
        $cores = [];
        $branches = [];
        foreach ($stored as $id => $programme) {
            if (($key = ProgrammeModel::coreKey($programme)) !== null) {
                $cores[$key][] = (int) $id;
            }
            if (($key = ProgrammeModel::branchKey($programme)) !== null) {
                $branches[$key][] = (int) $id;
            }
        }

        $shared = static fn (array $ids): bool => count($ids) > 1;

        return [array_filter($cores, $shared), array_filter($branches, $shared)];
    }

    /**
     * @param array<string, list<int>> $groups
     */
    private function validKey(?string $key, array $groups): ?string
    {
        return $key !== null && isset($groups[$key]) ? $key : null;
    }

    /**
     * @param array<string, list<int>> $cores
     * @param array<string, list<int>> $branches
     */
    private function optionStart(object $programme, array $cores, array $branches): int
    {
        $start = 1;
        if ($this->validKey(ProgrammeModel::coreKey($programme), $cores) !== null) {
            $start += ProgrammeModel::coreYears($programme);
            if ($this->validKey(ProgrammeModel::branchKey($programme), $branches) !== null) {
                $start += ProgrammeModel::branchYears($programme);
            }
        }

        return $start;
    }

    /**
     * Years of the option itself, or null when the programme has no trunk.
     *
     * @param array<string, list<int>> $cores
     * @param array<string, list<int>> $branches
     */
    private function optionYears(object $programme, array $cores, array $branches): ?string
    {
        $start = $this->optionStart($programme, $cores, $branches);
        if ($start === 1) {
            return null;
        }

        $level = (string) $programme->level;
        $total = ProgrammeModel::studyYears($programme);
        if ($total !== null && $total >= $start) {
            return $this->yearLabel($level, $start, $total);
        }

        return lang('Site.programmes.yearFrom', [$this->yearLabel($level, $start)]);
    }

    /**
     * @param list<int>          $ids
     * @param array<int, object> $stored
     * @param array<int, object> $departments
     *
     * @return list<object>
     */
    private function otherDepartments(array $ids, array $stored, array $departments, int $exceptId): array
    {
        $found = [];
        foreach ($ids as $id) {
            $departmentId = (int) ($stored[$id]->department_id ?? 0);
            if ($departmentId !== $exceptId && isset($departments[$departmentId])) {
                $found[$departmentId] = $departments[$departmentId];
            }
        }

        return array_values($found);
    }
}
