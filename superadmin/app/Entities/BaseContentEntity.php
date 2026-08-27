<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class BaseContentEntity extends Entity
{
    protected $casts = [
        'site_id' => '?integer',
    ];

    /**
     * @var list<string>
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'published_at',
        'event_starts_at',
        'event_ends_at',
        'read_at',
    ];
}
