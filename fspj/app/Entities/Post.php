<?php

namespace App\Entities;

class Post extends BaseContentEntity
{
    protected $casts = [
        'id'          => 'integer',
        'featured'    => 'int-bool',
        'home_order'  => '?integer',
        'created_by'  => '?integer',
        'updated_by'  => '?integer',
    ];
}
