<?php

namespace App\Models;

use App\Entities\TimelineItem;

class TimelineItemModel extends SiteScopedModel
{
    protected $table            = 'timeline_items';
    protected $primaryKey       = 'id';
    protected $returnType       = TimelineItem::class;
    protected $useTimestamps    = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['site_id', 'year', 'title', 'description', 'display_order', 'is_published'];
    protected array $casts      = [
        'id'            => 'integer',
        'year'          => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'year'          => 'required|integer',
        'title'         => 'required|max_length[255]',
        'description'   => 'required',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
