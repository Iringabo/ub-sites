<?php

namespace App\Entities;

class Department extends BaseContentEntity
{
    protected $casts = [
        'id'            => 'integer',
        'site_id'       => '?integer',
        'head_staff_id' => '?integer',
        'display_order' => 'integer',
        'is_published'  => 'int-bool',
    ];
}
