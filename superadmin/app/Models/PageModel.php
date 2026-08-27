<?php

namespace App\Models;

use App\Entities\Page;

class PageModel extends SiteScopedModel
{
    protected $table            = 'pages';
    protected $primaryKey       = 'id';
    protected $returnType       = Page::class;
    protected $useTimestamps    = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'key',
        'title',
        'slug',
        'content',
        'seo_title',
        'seo_description',
        'is_published',
    ];
    protected array $casts      = [
        'id'           => 'integer',
        'is_published' => 'boolean',
    ];
    protected $validationRules  = [
        'key'          => 'required|max_length[120]',
        'title'        => 'required|max_length[255]',
        'slug'         => 'required|max_length[180]|regex_match[/^[a-z0-9-]+$/]',
        'content'      => 'required',
        'is_published' => 'required|in_list[0,1]',
    ];
}
