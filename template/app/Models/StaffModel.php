<?php

namespace App\Models;

use App\Entities\StaffMember;

class StaffModel extends SiteScopedModel
{
    protected $table            = 'staff';
    protected $primaryKey       = 'id';
    protected $returnType       = StaffMember::class;
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'category',
        'name',
        'slug',
        'photo',
        'grade',
        'specialty',
        'role',
        'email',
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
        'category'      => 'required|in_list[enseignant,administratif]',
        'name'          => 'required|max_length[255]',
        'slug'          => 'required|max_length[180]|regex_match[/^[a-z0-9-]+$/]',
        'email'         => 'permit_empty|valid_email|max_length[255]',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
