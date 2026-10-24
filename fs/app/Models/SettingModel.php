<?php

namespace App\Models;

use App\Entities\Setting;

class SettingModel extends SiteScopedModel
{
    protected $table            = 'settings';
    protected $primaryKey       = 'id';
    protected $returnType       = Setting::class;
    protected $useTimestamps    = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['site_id', 'class', 'key', 'value', 'type', 'context'];
    protected array $casts      = [
        'id' => 'integer',
    ];
    protected $validationRules  = [
        'class'   => 'required|max_length[255]',
        'key'     => 'required|max_length[160]|regex_match[/^[a-z0-9_.-]+$/]',
        'value'   => 'permit_empty',
        'type'    => 'required|in_list[string,text,url,email,color,integer,boolean,json,path]',
        'context' => 'permit_empty|max_length[80]',
    ];
}
