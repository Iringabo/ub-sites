<?php

namespace App\Entities;

class SiteStat extends BaseContentEntity
{
    protected $casts = [
        'id'            => 'integer',
        'value'         => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'int-bool',
    ];
}
