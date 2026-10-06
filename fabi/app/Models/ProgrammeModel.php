<?php

namespace App\Models;

use App\Entities\Programme;

class ProgrammeModel extends SiteScopedModel
{
    protected $table            = 'programmes';
    protected $primaryKey       = 'id';
    protected $returnType       = Programme::class;
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'level',
        'department_id',
        'option_code',
        'common_core',
        'common_core_years',
        'branch_core',
        'branch_core_years',
        'title',
        'slug',
        'duration',
        'language',
        'credits',
        'entry_requirement',
        'application_deadline',
        'summary',
        'description',
        'admission_conditions',
        'career_outcomes',
        'curriculum',
        'coordinator_staff_id',
        'brochure_path',
        'apply_url',
        'display_order',
        'featured_on_home',
        'home_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'               => 'integer',
        'career_outcomes'  => 'json-array',
        'curriculum'       => '?json-array',
        'department_id'    => '?integer',
        'common_core_years' => '?integer',
        'branch_core_years' => '?integer',
        'credits'          => '?integer',
        'coordinator_staff_id' => '?integer',
        'display_order'    => 'integer',
        'featured_on_home' => 'boolean',
        'home_order'       => '?integer',
        'is_published'     => 'boolean',
    ];
    protected $validationRules  = [
        'level'                => 'required|in_list[licence,master]',
        'title'                => 'required|max_length[255]',
        'slug'                 => 'required|max_length[180]|regex_match[/^[a-z0-9-]+$/]',
        'duration'             => 'required|max_length[80]',
        'summary'              => 'required',
        'description'          => 'required',
        'admission_conditions' => 'required',
        'department_id'        => 'permit_empty|integer',
        'option_code'          => 'permit_empty|max_length[20]',
        'common_core'          => 'permit_empty|max_length[120]',
        'common_core_years'    => 'permit_empty|integer|greater_than[0]|less_than[6]',
        'branch_core'          => 'permit_empty|max_length[120]',
        'branch_core_years'    => 'permit_empty|integer|greater_than[0]|less_than[6]',
        'language'             => 'permit_empty|max_length[120]',
        'credits'              => 'permit_empty|integer',
        'entry_requirement'    => 'permit_empty|max_length[255]',
        'application_deadline' => 'permit_empty|valid_date[Y-m-d]',
        'coordinator_staff_id' => 'permit_empty|integer',
        'brochure_path'        => 'permit_empty|max_length[500]',
        'apply_url'            => 'permit_empty|valid_url_strict|max_length[500]',
        'display_order'        => 'required|integer',
        'featured_on_home'     => 'required|in_list[0,1]',
        'home_order'           => 'permit_empty|integer',
        'is_published'         => 'required|in_list[0,1]',
    ];

    /**
     * Years spent in the first tronc commun when none is given: Bac I–II in
     * licence, the first year in master.
     */
    public const DEFAULT_CORE_YEARS = ['licence' => 2, 'master' => 1];

    /**
     * Programme id => ids of the other programmes sharing its tronc commun:
     * same site, level and trunk name, whatever the department. Pass
     * untranslated records: the trunk name is matched on its stored value.
     *
     * @param iterable<Programme> $programmes
     *
     * @return array<int, list<int>>
     */
    public static function commonCorePeerIds(iterable $programmes): array
    {
        return self::peerIds($programmes, static fn (object $programme): ?string => self::coreKey($programme));
    }

    /**
     * Same as commonCorePeerIds() for the narrower trunk that follows the
     * first one (for example Bac II shared by two options only).
     *
     * @param iterable<Programme> $programmes
     *
     * @return array<int, list<int>>
     */
    public static function branchCorePeerIds(iterable $programmes): array
    {
        return self::peerIds($programmes, static fn (object $programme): ?string => self::branchKey($programme));
    }

    public static function coreKey(object $programme): ?string
    {
        $core = trim((string) ($programme->common_core ?? ''));

        return $core === '' ? null : $programme->level . '|' . mb_strtolower($core);
    }

    public static function branchKey(object $programme): ?string
    {
        $core = self::coreKey($programme);
        $branch = trim((string) ($programme->branch_core ?? ''));

        return $core === null || $branch === '' ? null : $core . '|' . mb_strtolower($branch);
    }

    public static function coreYears(object $programme): int
    {
        $years = (int) ($programme->common_core_years ?? 0);

        return $years > 0 ? $years : (self::DEFAULT_CORE_YEARS[(string) $programme->level] ?? 1);
    }

    public static function branchYears(object $programme): int
    {
        return max(1, (int) ($programme->branch_core_years ?? 0));
    }

    /**
     * Length of the programme in years: credits / 60 when credits are set,
     * otherwise the first number found in the duration ("4 ans").
     */
    public static function studyYears(object $programme): ?int
    {
        $credits = (int) ($programme->credits ?? 0);
        if ($credits >= 60) {
            return (int) round($credits / 60);
        }

        if (preg_match('/\d+/', (string) ($programme->duration ?? ''), $match) === 1 && (int) $match[0] > 0) {
            return (int) $match[0];
        }

        return null;
    }

    /**
     * @param iterable<Programme>          $programmes
     * @param callable(object): ?string $key
     *
     * @return array<int, list<int>>
     */
    private static function peerIds(iterable $programmes, callable $key): array
    {
        $groups = [];
        foreach ($programmes as $programme) {
            $group = $key($programme);
            if ($group !== null) {
                $groups[$group][] = (int) $programme->id;
            }
        }

        $peers = [];
        foreach ($groups as $ids) {
            if (count($ids) < 2) {
                continue;
            }

            foreach ($ids as $id) {
                $peers[$id] = array_values(array_diff($ids, [$id]));
            }
        }

        return $peers;
    }
}
