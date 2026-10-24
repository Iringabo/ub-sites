<?php

namespace App\Models;

use App\Entities\Publication;

class PublicationModel extends SiteScopedModel
{
    protected $table            = 'publications';
    protected $primaryKey       = 'id';
    protected $returnType       = Publication::class;
    protected $useTimestamps    = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['site_id', 'year', 'title', 'authors', 'journal', 'url', 'display_order', 'is_published'];
    protected array $casts      = [
        'id'            => 'integer',
        'year'          => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'year'          => 'required|integer',
        'title'         => 'required|max_length[500]',
        'authors'       => 'required',
        'journal'       => 'required|max_length[255]',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
