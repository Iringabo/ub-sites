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
        'title',
        'slug',
        'duration',
        'summary',
        'description',
        'admission_conditions',
        'career_outcomes',
        'display_order',
        'featured_on_home',
        'home_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'               => 'integer',
        'career_outcomes'  => 'json-array',
        'display_order'    => 'integer',
        'featured_on_home' => 'boolean',
        'home_order'       => '?integer',
        'is_published'     => 'boolean',
    ];
    protected $validationRules  = [
        'level'                => 'required|in_list[licence,master,doctorat]',
        'title'                => 'required|max_length[255]',
        'slug'                 => 'required|max_length[180]|regex_match[/^[a-z0-9-]+$/]',
        'duration'             => 'required|max_length[80]',
        'summary'              => 'required',
        'description'          => 'required',
        'admission_conditions' => 'required',
        'display_order'        => 'required|integer',
        'featured_on_home'     => 'required|in_list[0,1]',
        'home_order'           => 'permit_empty|integer',
        'is_published'         => 'required|in_list[0,1]',
    ];
}
