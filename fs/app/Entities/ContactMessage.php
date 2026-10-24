<?php

namespace App\Entities;

class ContactMessage extends BaseContentEntity
{
    protected $casts = [
        'id'           => 'integer',
        'processed_by'  => 'integer',
    ];
}
