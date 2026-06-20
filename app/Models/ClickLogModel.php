<?php

namespace App\Models;

use CodeIgniter\Model;

class ClickLogModel extends Model
{
    protected $table            = 'click_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'url_id',
        'ip_address',
        'user_agent',
        'referrer',
        'clicked_at'
    ];

    protected $useTimestamps = false;
}
