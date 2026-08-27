<?php

namespace App\Models;

use App\Entities\ResearchProject;

class ResearchProjectModel extends SiteScopedModel
{
    protected $table            = 'research_projects';
    protected $primaryKey       = 'id';
    protected $returnType       = ResearchProject::class;
    protected $useTimestamps    = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'code',
        'title',
        'description',
        'funder',
        'period_start',
        'period_end',
        'icon',
        'display_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'            => 'integer',
        'period_start'  => '?integer',
        'period_end'    => '?integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'code'          => 'required|max_length[80]',
        'title'         => 'required|max_length[255]',
        'description'   => 'required',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
