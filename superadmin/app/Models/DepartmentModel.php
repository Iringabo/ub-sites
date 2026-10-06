<?php

namespace App\Models;

use App\Entities\Department;

class DepartmentModel extends SiteScopedModel
{
    /**
     * Licence departments often differ from master ones.
     */
    public const CYCLES = ['licence', 'master', 'both'];

    protected $table            = 'departments';
    protected $primaryKey       = 'id';
    protected $returnType       = Department::class;
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'name',
        'slug',
        'abbreviation',
        'cycle',
        'description',
        'head_staff_id',
        'email',
        'phone',
        'maquette_path',
        'maquette_title',
        'display_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'            => 'integer',
        'head_staff_id' => '?integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'name'          => 'required|max_length[255]',
        'slug'          => 'required|max_length[180]|regex_match[/^[a-z0-9-]+$/]',
        'abbreviation'  => 'permit_empty|max_length[40]',
        'cycle'         => 'permit_empty|in_list[licence,master,both]',
        'head_staff_id' => 'permit_empty|integer',
        'email'         => 'permit_empty|valid_email|max_length[255]',
        'phone'         => 'permit_empty|max_length[80]',
        'maquette_path' => 'permit_empty|max_length[500]',
        'maquette_title' => 'permit_empty|max_length[255]',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];

    /**
     * Whether a department of this cycle may host a programme of this level.
     */
    public static function cycleAccepts(?string $cycle, string $level): bool
    {
        return match ($cycle ?: 'both') {
            'licence' => $level === 'licence',
            'master'  => $level === 'master',
            default   => true,
        };
    }
}
