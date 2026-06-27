<?php

namespace App\Models;

use CodeIgniter\Model;

class UrlModel extends Model
{
    protected $table            = 'urls';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'short_code',
        'original_url',
        'custom_alias',
        'user_id',
        'idempotency_key',
        'clicks_count',
        'expires_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['encryptUrl'];
    protected $beforeUpdate   = ['encryptUrl'];
    protected $afterFind      = ['decryptUrl'];

    protected function encryptUrl(array $data)
    {
        if (isset($data['data']['original_url'])) {
            $encrypter = \Config\Services::encrypter();
            // Encrypt and encode to base64 for safe text storage
            $data['data']['original_url'] = base64_encode($encrypter->encrypt($data['data']['original_url']));
        }
        return $data;
    }

    protected function decryptUrl(array $data)
    {
        if (empty($data['data'])) {
            return $data;
        }

        $encrypter = \Config\Services::encrypter();

        // If multiple rows are returned (e.g. findAll)
        if (isset($data['data'][0])) {
            foreach ($data['data'] as &$row) {
                if (isset($row['original_url'])) {
                    try {
                        $decrypted = $encrypter->decrypt(base64_decode($row['original_url']));
                        $row['original_url'] = $decrypted;
                    } catch (\Throwable $e) {
                        // Decryption failed (e.g., old plaintext data), leave as is
                    }
                }
            }
        } 
        // Single row returned (e.g. find or first)
        else if (isset($data['data']['original_url'])) {
            try {
                $decrypted = $encrypter->decrypt(base64_decode($data['data']['original_url']));
                $data['data']['original_url'] = $decrypted;
            } catch (\Throwable $e) {
                // Decryption failed (e.g., old plaintext data), leave as is
            }
        }

        return $data;
    }
}
