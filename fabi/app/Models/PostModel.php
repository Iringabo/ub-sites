<?php

namespace App\Models;

use App\Entities\Post;

class PostModel extends SiteScopedModel
{
    protected $table            = 'posts';
    protected $primaryKey       = 'id';
    protected $returnType       = Post::class;
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'type',
        'title',
        'slug',
        'excerpt',
        'body',
        'cover_image',
        'status',
        'published_at',
        'featured',
        'home_order',
        'event_starts_at',
        'event_ends_at',
        'event_location',
        'registration_url',
        'seo_title',
        'seo_description',
        'created_by',
        'updated_by',
    ];
    protected array $casts      = [
        'id'              => 'integer',
        'featured'        => 'boolean',
        'home_order'      => '?integer',
        'created_by'      => '?integer',
        'updated_by'      => '?integer',
    ];
    protected $validationRules  = [
        'type'        => 'required|in_list[news,event]',
        'title'       => 'required|max_length[255]',
        'slug'        => 'required|max_length[180]|regex_match[/^[a-z0-9-]+$/]',
        'excerpt'     => 'required',
        'body'        => 'required',
        'status'      => 'required|in_list[draft,scheduled,published,archived]',
        'featured'    => 'required|in_list[0,1]',
        'home_order'  => 'permit_empty|integer',
    ];

    public function visible(): self
    {
        return service('postVisibilityService')->applyVisible($this->forSite());
    }
}
