<?php

namespace App\Entities;

class AlumniProfile extends BaseContentEntity
{
    protected $casts = [
        'id'            => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'int-bool',
    ];
}
