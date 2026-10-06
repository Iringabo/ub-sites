<?php

namespace App\Entities;

class FaqItem extends BaseContentEntity
{
    protected $casts = [
        'id'            => 'integer',
        'site_id'       => '?integer',
        'display_order' => 'integer',
        'is_published'  => 'int-bool',
    ];
}
