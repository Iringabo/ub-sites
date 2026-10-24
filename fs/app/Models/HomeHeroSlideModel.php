<?php

namespace App\Models;

use App\Entities\HomeHeroSlide;

class HomeHeroSlideModel extends SiteScopedModel
{
    protected $table            = 'home_hero_slides';
    protected $primaryKey       = 'id';
    protected $returnType       = HomeHeroSlide::class;
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'image_path',
        'alt_text',
        'badge',
        'title',
        'text',
        'primary_cta_target',
        'primary_cta_label',
        'primary_cta_url',
        'secondary_cta_target',
        'secondary_cta_label',
        'secondary_cta_url',
        'display_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'            => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'image_path'           => 'required|max_length[500]',
        'alt_text'             => 'required|max_length[255]',
        'badge'                => 'permit_empty|max_length[255]',
        'title'                => 'required|max_length[255]',
        'text'                 => 'required',
        'primary_cta_target'   => 'required|in_list[none,programmes,contact,news,about,faculty_profile,custom]',
        'primary_cta_label'    => 'permit_empty|max_length[255]',
        'primary_cta_url'      => 'permit_empty|max_length[500]',
        'secondary_cta_target' => 'required|in_list[none,programmes,contact,news,about,faculty_profile,custom]',
        'secondary_cta_label'  => 'permit_empty|max_length[255]',
        'secondary_cta_url'    => 'permit_empty|max_length[500]',
        'display_order'        => 'required|integer',
        'is_published'         => 'required|in_list[0,1]',
    ];
}
