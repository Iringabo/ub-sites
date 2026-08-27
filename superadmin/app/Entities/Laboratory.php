<?php

namespace App\Entities;

class Laboratory extends BaseContentEntity
{
    protected $casts = [
        'id'               => 'integer',
        'researcher_count' => 'integer',
        'display_order'    => 'integer',
        'featured_on_home' => 'int-bool',
        'home_order'       => '?integer',
        'is_published'     => 'int-bool',
    ];
}
