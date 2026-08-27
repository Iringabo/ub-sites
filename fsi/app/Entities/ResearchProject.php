<?php

namespace App\Entities;

class ResearchProject extends BaseContentEntity
{
    protected $casts = [
        'id'            => 'integer',
        'period_start'  => '?integer',
        'period_end'    => '?integer',
        'display_order' => 'integer',
        'is_published'  => 'int-bool',
    ];
}
