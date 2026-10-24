<?php

namespace App\Models;

use App\Entities\HomeHighlight;

class HomeHighlightModel extends SiteScopedModel
{
    protected $table            = 'home_highlights';
    protected $primaryKey       = 'id';
    protected $returnType       = HomeHighlight::class;
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['site_id', 'icon', 'title', 'description', 'display_order', 'is_published'];
    protected array $casts      = [
        'id'            => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'icon'          => 'required|max_length[80]',
        'title'         => 'required|max_length[255]',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
