<?php

namespace App\Entities;

class Page extends BaseContentEntity
{
    protected $casts = [
        'id'           => 'integer',
        'is_published' => 'int-bool',
    ];
}
