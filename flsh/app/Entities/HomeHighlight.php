<?php

namespace App\Entities;

class HomeHighlight extends BaseContentEntity
{
    protected $casts = [
        'id'            => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'int-bool',
    ];
}
