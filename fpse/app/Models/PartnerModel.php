<?php

namespace App\Models;

use App\Entities\Partner;

class PartnerModel extends SiteScopedModel
{
    public const TYPES = ['academic', 'industry', 'institution', 'ngo'];

    protected $table            = 'partners';
    protected $primaryKey       = 'id';
    protected $returnType       = Partner::class;
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'name',
        'type',
        'logo',
        'country',
        'url',
        'description',
        'display_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'            => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'name'          => 'required|max_length[255]',
        'type'          => 'required|in_list[academic,industry,institution,ngo]',
        'country'       => 'permit_empty|max_length[120]',
        'url'           => 'permit_empty|valid_url_strict|max_length[500]',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
