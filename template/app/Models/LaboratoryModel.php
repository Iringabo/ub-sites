<?php

namespace App\Models;

use App\Entities\Laboratory;

class LaboratoryModel extends SiteScopedModel
{
    protected $table            = 'laboratories';
    protected $primaryKey       = 'id';
    protected $returnType       = Laboratory::class;
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'abbreviation',
        'name',
        'slug',
        'icon',
        'description',
        'themes',
        'researcher_count',
        'display_order',
        'featured_on_home',
        'home_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'               => 'integer',
        'themes'           => 'json-array',
        'researcher_count' => 'integer',
        'display_order'    => 'integer',
        'featured_on_home' => 'boolean',
        'home_order'       => '?integer',
        'is_published'     => 'boolean',
    ];
    protected $validationRules  = [
        'abbreviation'     => 'required|max_length[40]',
        'name'             => 'required|max_length[255]',
        'slug'             => 'required|max_length[180]|regex_match[/^[a-z0-9-]+$/]',
        'description'      => 'required',
        'researcher_count' => 'required|integer',
        'display_order'    => 'required|integer',
        'featured_on_home' => 'required|in_list[0,1]',
        'home_order'       => 'permit_empty|integer',
        'is_published'     => 'required|in_list[0,1]',
    ];
}
