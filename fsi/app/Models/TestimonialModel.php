<?php

namespace App\Models;

use App\Entities\Testimonial;

class TestimonialModel extends SiteScopedModel
{
    protected $table            = 'testimonials';
    protected $primaryKey       = 'id';
    protected $returnType       = Testimonial::class;
    protected $useTimestamps    = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'alumni_profile_id',
        'person_name',
        'photo',
        'promotion',
        'quote',
        'display_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'                => 'integer',
        'alumni_profile_id' => '?integer',
        'display_order'     => 'integer',
        'is_published'      => 'boolean',
    ];
    protected $validationRules  = [
        'person_name'   => 'required|max_length[255]',
        'quote'         => 'required',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
