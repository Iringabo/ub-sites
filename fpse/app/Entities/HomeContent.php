<?php

namespace App\Entities;

class HomeContent extends BaseContentEntity
{
    protected $casts = [
        'id'            => 'integer',
        'singleton_key' => 'integer',
        'updated_by'    => '?integer',
    ];
}
