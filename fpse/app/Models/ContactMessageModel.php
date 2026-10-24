<?php

namespace App\Models;

use App\Entities\ContactMessage;

class ContactMessageModel extends SiteScopedModel
{
    protected $table            = 'contact_messages';
    protected $primaryKey       = 'id';
    protected $returnType       = ContactMessage::class;
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'site_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'ip_address',
        'user_agent',
        'read_at',
        'processed_by',
    ];
    protected array $casts      = [
        'id'           => 'integer',
        'processed_by' => '?integer',
    ];
    protected $validationRules  = [
        'name'    => 'required|max_length[255]',
        'email'   => 'required|valid_email|max_length[255]',
        'phone'   => 'permit_empty|max_length[80]',
        'subject' => 'required|max_length[255]',
        'message' => 'required|max_length[5000]',
        'status'  => 'required|in_list[new,read,handled,archived]',
    ];
}
