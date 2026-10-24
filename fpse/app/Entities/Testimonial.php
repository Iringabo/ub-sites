<?php

namespace App\Entities;

class Testimonial extends BaseContentEntity
{
    protected $casts = [
        'id'                => 'integer',
        'alumni_profile_id' => '?integer',
        'display_order'     => 'integer',
        'is_published'      => 'int-bool',
    ];
}
