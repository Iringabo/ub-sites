<?php

namespace App\Models;

use App\Entities\AlumniProfile;

class AlumniProfileModel extends SiteScopedModel
{
    protected $table            = 'alumni_profiles';
    protected $primaryKey       = 'id';
    protected $returnType       = AlumniProfile::class;
    protected $useTimestamps    = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'name',
        'slug',
        'photo',
        'promotion',
        'role',
        'organization',
        'biography',
        'display_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'            => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'name'          => 'required|max_length[255]',
        'slug'          => 'required|max_length[180]|regex_match[/^[a-z0-9-]+$/]',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
