<?php

namespace App\Models;

use CodeIgniter\Model;

class UserSiteModel extends Model
{
    protected $table            = 'user_sites';
    protected $primaryKey       = 'id';
    protected $returnType       = 'object';
    protected $useTimestamps    = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'site_id',
        'role',
    ];
    protected array $casts      = [
        'id'      => 'integer',
        'user_id' => 'integer',
        'site_id' => 'integer',
    ];
    protected $validationRules  = [
        'user_id' => 'required|integer',
        'site_id' => 'required|integer',
        'role'    => 'permit_empty|max_length[80]',
    ];
}
