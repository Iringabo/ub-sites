<?php

namespace App\Models;

use App\Entities\FaqItem;

class FaqItemModel extends SiteScopedModel
{
    public const CATEGORIES = ['admissions', 'fees', 'studies', 'other'];

    protected $table            = 'faq_items';
    protected $primaryKey       = 'id';
    protected $returnType       = FaqItem::class;
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'category',
        'question',
        'answer',
        'display_order',
        'is_published',
    ];
    protected array $casts      = [
        'id'            => 'integer',
        'display_order' => 'integer',
        'is_published'  => 'boolean',
    ];
    protected $validationRules  = [
        'category'      => 'required|in_list[admissions,fees,studies,other]',
        'question'      => 'required|max_length[500]',
        'answer'        => 'required',
        'display_order' => 'required|integer',
        'is_published'  => 'required|in_list[0,1]',
    ];
}
