<?php

namespace App\Models;

use App\Entities\ContentBlock;

class ContentBlockModel extends SiteScopedModel
{
    protected $table            = 'content_blocks';
    protected $primaryKey       = 'id';
    protected $returnType       = ContentBlock::class;
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'page_key',
        'type',
        'title',
        'content',
        'settings',
        'display_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'            => 'integer',
        'settings'      => 'json-array',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'page_key'      => 'required|max_length[120]|regex_match[/^[a-z0-9_-]+$/]',
        'type'          => 'required|in_list[hero,dean_message,programmes_preview,news_preview,research_labs,staff_preview,statistics,custom_text,image_gallery,contact_cta]',
        'title'         => 'permit_empty|max_length[255]',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
