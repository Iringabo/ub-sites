<?php

namespace App\Models;

use App\Entities\HomeContent;

class HomeContentModel extends SiteScopedModel
{
    protected $table            = 'home_content';
    protected $primaryKey       = 'id';
    protected $returnType       = HomeContent::class;
    protected $useTimestamps    = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'singleton_key',
        'hero_badge',
        'hero_title',
        'hero_text',
        'hero_media_type',
        'hero_media_path',
        'hero_primary_label',
        'hero_primary_url',
        'hero_secondary_label',
        'hero_secondary_url',
        'about_label',
        'about_title',
        'about_body',
        'about_button_label',
        'about_button_url',
        'research_label',
        'research_title',
        'research_body',
        'research_button_label',
        'research_button_url',
        'programmes_label',
        'programmes_title',
        'programmes_text',
        'programmes_button_label',
        'programmes_button_url',
        'posts_label',
        'posts_title',
        'posts_text',
        'posts_button_label',
        'posts_button_url',
        'seo_title',
        'seo_description',
        'updated_by',
    ];
    protected array $casts      = [
        'id'            => 'integer',
        'singleton_key' => 'integer',
        'updated_by'    => '?integer',
    ];
    protected $validationRules  = [
        'singleton_key'   => 'required|integer',
        'hero_badge'      => 'required|max_length[255]',
        'hero_title'      => 'required|max_length[255]',
        'hero_text'       => 'required',
        'hero_media_type' => 'required|in_list[video,image]',
        'hero_media_path' => 'required|max_length[500]',
        'seo_title'       => 'required|max_length[255]',
        'seo_description' => 'required',
    ];
}
