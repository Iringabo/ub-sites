<?php

namespace App\Models;

use App\Entities\SiteStat;

class SiteStatModel extends SiteScopedModel
{
    protected $table            = 'site_stats';
    protected $primaryKey       = 'id';
    protected $returnType       = SiteStat::class;
    protected $useTimestamps    = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['site_id', 'section', 'label', 'value', 'suffix', 'display_order', 'is_published'];
    protected array $casts      = [
        'id'            => 'integer',
        'value'         => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'section'       => 'required|max_length[80]',
        'label'         => 'required|max_length[255]',
        'value'         => 'required|integer',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
