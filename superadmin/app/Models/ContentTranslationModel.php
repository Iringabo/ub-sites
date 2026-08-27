<?php

namespace App\Models;

class ContentTranslationModel extends SiteScopedModel
{
    protected $table         = 'content_translations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'site_id',
        'resource_type',
        'resource_id',
        'locale',
        'field',
        'value',
    ];
    protected array $casts = [
        'id'          => 'integer',
        'resource_id' => 'integer',
    ];
}
