<?php

namespace App\Entities;

class ContentBlock extends BaseContentEntity
{
    protected $casts = [
        'id'            => 'integer',
        'site_id'       => 'integer',
        'settings'      => 'json-array',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
}
