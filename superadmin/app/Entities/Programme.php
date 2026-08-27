<?php

namespace App\Entities;

class Programme extends BaseContentEntity
{
    protected $casts = [
        'id'               => 'integer',
        'display_order'    => 'integer',
        'featured_on_home' => 'int-bool',
        'home_order'       => '?integer',
        'is_published'     => 'int-bool',
    ];
}
