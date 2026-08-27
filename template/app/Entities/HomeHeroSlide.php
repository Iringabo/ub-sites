<?php

namespace App\Entities;

class HomeHeroSlide extends BaseContentEntity
{
    protected $casts = [
        'id'            => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
}
