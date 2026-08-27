<?php

namespace App\Models;

use App\Entities\Site;
use CodeIgniter\Model;

class SiteModel extends Model
{
    protected $table            = 'sites';
    protected $primaryKey       = 'id';
    protected $returnType       = Site::class;
    protected $useTimestamps    = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'identifier',
        'name',
        'slug',
        'hostnames',
        'status',
        'default_locale',
        'logo',
        'primary_color',
        'secondary_color',
        'theme',
        'theme_config',
        'menu_config',
        'enabled_sections',
        'contact_email',
        'phone',
        'address',
    ];
    protected array $casts      = [
        'id'               => 'integer',
        'hostnames'        => '?json-array',
        'enabled_sections' => '?json-array',
    ];
    protected $validationRules  = [
        'identifier'      => 'required|max_length[80]|regex_match[/^[a-z0-9_.-]+$/]|is_unique[sites.identifier,id,{id}]',
        'name'            => 'required|max_length[255]',
        'slug'            => 'required|max_length[120]|regex_match[/^[a-z0-9-]+$/]|is_unique[sites.slug,id,{id}]',
        'status'          => 'required|in_list[active,inactive]',
        'default_locale'  => 'required|in_list[fr,en]',
        'primary_color'   => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
        'secondary_color' => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
        'theme'           => 'permit_empty|in_list[default,institutional,modern,research]',
        'contact_email'   => 'permit_empty|valid_email|max_length[255]',
    ];
}
