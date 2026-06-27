<?php

namespace App\Controllers;

use App\Models\UrlModel;
use App\Libraries\Base62Encoder;
use App\Libraries\RedisService;
use CodeIgniter\API\ResponseTrait;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class ShortenController extends BaseController
{
    use ResponseTrait;

    public function shorten()
    {
        $rules = [
            'original_url' => 'required|valid_url',
            'custom_alias' => 'permit_empty|alpha_dash|min_length[3]|max_length[50]',
            'expires_at'   => 'permit_empty|valid_date[Y-m-d H:i:s]'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Validasi gagal.',
                'data'    => $this->validator->getErrors()
            ], 400);
        }

        $originalUrl = $this->request->getVar('original_url');
        $customAlias = $this->request->getVar('custom_alias');
        $expiresAt   = $this->request->getVar('expires_at');
        
        // Cek expiration date harus di masa depan jika diisi
        if (!empty($expiresAt)) {
            if (strtotime($expiresAt) < time()) {
                return $this->respond([
                    'status'  => 'error',
                    'message' => 'Waktu kedaluwarsa harus di masa depan.',
                    'data'    => ['expires_at' => 'Waktu harus di masa depan.']
                ], 400);
            }
        }

        // Cek Auth JWT (opsional)
        $userId = null;
        $authHeader = $this->request->getServer('HTTP_AUTHORIZATION');
        if (!empty($authHeader)) {
            $arr = explode(" ", $authHeader);
            $token = isset($arr[1]) ? $arr[1] : $arr[0];
            if (!empty($token)) {
                try {
                    $key = env('jwt.secret', 'super-secret-key-change-in-production-12345');
                    $decoded = JWT::decode($token, new Key($key, 'HS256'));
                    $userId = $decoded->data->id;
                } catch (Exception $e) {
                    return $this->respond([
                        'status'  => 'error',
                        'message' => 'Token tidak valid atau kedaluwarsa.',
                        'data'    => null
                    ], 401);
                }
            }
        }

        // Cek Idempotency Key
        $idempotencyKey = $this->request->getHeaderLine('Idempotency-Key');
        $urlModel = new UrlModel();
        
        if (!empty($idempotencyKey)) {
            $existing = $urlModel->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                // Return exact same data as created before
                return $this->respond([
                    'status'  => 'success',
                    'message' => 'Data ditemukan (Idempotent).',
                    'data'    => $this->formatUrlResponse($existing)
                ], 200);
            }
        }

        // Tentukan short code
        $shortCode = '';
        
        if (!empty($customAlias)) {
            // Coba gunakan alias kustom secara langsung
            $exists = $urlModel->where('short_code', $customAlias)->first();
            
            if (!$exists) {
                // Belum ada yang pakai, gunakan murni
                $shortCode = $customAlias;
            } else {
                // Sudah dipakai user lain! Jangan dilarang, tapi tambahkan suffix unik
                $attempts = 0;
                do {
                    // contoh: promo-aB3x
                    $shortCode = $customAlias . '-' . substr(Base62Encoder::generate(), 0, 4);
                    $exists2 = $urlModel->where('short_code', $shortCode)->first();
                    $attempts++;
                } while ($exists2 && $attempts < 5);
                
                if ($exists2) {
                    return $this->respond([
                        'status'  => 'error',
                        'message' => 'Gagal membuat kombinasi unik untuk alias kustom Anda.',
                        'data'    => null
                    ], 500);
                }
            }
        } else {
            // Generate collision-safe short code acak murni
            $attempts = 0;
            do {
                $shortCode = Base62Encoder::generate();
                $exists = $urlModel->where('short_code', $shortCode)->first();
                $attempts++;
            } while ($exists && $attempts < 5);
            
            if ($exists) {
                return $this->respond([
                    'status'  => 'error',
                    'message' => 'Gagal membuat short code yang unik, silakan coba lagi.',
                    'data'    => null
                ], 500);
            }
        }

        // Simpan ke database
        $data = [
            'short_code'      => $shortCode,
            'original_url'    => $originalUrl,
            'custom_alias'    => !empty($customAlias) ? $customAlias : null,
            'user_id'         => $userId,
            'idempotency_key' => !empty($idempotencyKey) ? $idempotencyKey : null,
            'clicks_count'    => 0,
            'expires_at'      => !empty($expiresAt) ? $expiresAt : null,
        ];

        try {
            $insertedId = $urlModel->insert($data);
            $data['id'] = $insertedId;

            // Cache-aside warming: Caching ke Redis
            $redis = new RedisService();
            if ($redis->isEnabled()) {
                $cacheKey = 'url:' . $shortCode;
                $ttl = null;
                if (!empty($expiresAt)) {
                    $ttl = strtotime($expiresAt) - time();
                } else {
                    $ttl = 604800; // default cache 7 hari
                }
                if ($ttl > 0) {
                    $redis->set($cacheKey, json_encode($data), $ttl);
                }
            }

            return $this->respond([
                'status'  => 'success',
                'message' => 'Short URL berhasil dibuat.',
                'data'    => $this->formatUrlResponse($data)
            ], 201);

        } catch (Exception $e) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Gagal membuat short URL: ' . $e->getMessage(),
                'data'    => null
            ], 500);
        }
    }

    private function formatUrlResponse(array $url)
    {
        $baseURL = base_url();
        return [
            'id'           => (int) $url['id'],
            'short_code'   => $url['short_code'],
            'short_url'    => rtrim($baseURL, '/') . '/' . $url['short_code'],
            'original_url' => $url['original_url'],
            'custom_alias' => $url['custom_alias'],
            'clicks_count' => (int) ($url['clicks_count'] ?? 0),
            'expires_at'   => $url['expires_at'],
            'created_at'   => $url['created_at'] ?? null
        ];
    }
}
