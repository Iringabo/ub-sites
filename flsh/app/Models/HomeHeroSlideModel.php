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
        'display_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'            => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'image_path'    => 'required|max_length[500]',
        'alt_text'      => 'required|max_length[255]',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
