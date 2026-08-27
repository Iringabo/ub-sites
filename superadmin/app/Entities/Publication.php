<?php

namespace App\Entities;

class Publication extends BaseContentEntity
{
    protected $casts = [
        'id'            => 'integer',
        'year'          => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'int-bool',
    ];
}
