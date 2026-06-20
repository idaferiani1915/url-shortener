<?php

namespace App\Controllers;

use App\Models\UrlModel;
use App\Libraries\RedisService;
use CodeIgniter\API\ResponseTrait;
use Exception;

class UrlController extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $userId = $this->request->user['id'];
        $urlModel = new UrlModel();

        $page  = $this->request->getVar('page') ? (int) $this->request->getVar('page') : 1;
        $limit = $this->request->getVar('limit') ? (int) $this->request->getVar('limit') : 10;

        $urls = $urlModel->where('user_id', $userId)
                         ->orderBy('created_at', 'DESC')
                         ->paginate($limit, 'default', $page);

        $pager = $urlModel->pager;

        $formattedUrls = [];
        $baseURL = base_url();
        foreach ($urls as $url) {
            $formattedUrls[] = [
                'id'           => (int) $url['id'],
                'short_code'   => $url['short_code'],
                'short_url'    => rtrim($baseURL, '/') . '/' . $url['short_code'],
                'original_url' => $url['original_url'],
                'custom_alias' => $url['custom_alias'],
                'clicks_count' => (int) $url['clicks_count'],
                'expires_at'   => $url['expires_at'],
                'created_at'   => $url['created_at'],
                'updated_at'   => $url['updated_at']
            ];
        }

        return $this->respond([
            'status'  => 'success',
            'message' => 'Daftar URL berhasil dimuat.',
            'data'    => [
                'urls' => $formattedUrls,
                'pagination' => [
                    'current_page' => $pager->getCurrentPage(),
                    'total_pages'  => $pager->getPageCount(),
                    'total_items'  => $pager->getTotal(),
                    'per_page'     => $pager->getPerPage()
                ]
            ]
        ], 200);
    }

    public function update(int $id)
    {
        $userId = $this->request->user['id'];
        $urlModel = new UrlModel();
        
        $url = $urlModel->where('id', $id)->first();
        if (!$url || $url['user_id'] != $userId) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'URL tidak ditemukan atau Anda tidak memiliki akses.',
                'data'    => null
            ], 404);
        }

        $rules = [
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

        $customAlias = $this->request->getVar('custom_alias');
        $expiresAt   = $this->request->getVar('expires_at');
        
        if (!empty($expiresAt)) {
            if (strtotime($expiresAt) < time()) {
                return $this->respond([
                    'status'  => 'error',
                    'message' => 'Waktu kedaluwarsa harus di masa depan.',
                    'data'    => ['expires_at' => 'Waktu harus di masa depan.']
                ], 400);
            }
        }

        $oldShortCode = $url['short_code'];
        $newShortCode = $oldShortCode;

        // Cek jika alias diubah
        if (!empty($customAlias) && $customAlias !== $url['custom_alias']) {
            // Validasi keunikan alias baru
            $exists = $urlModel->where('id !=', $id)
                               ->groupStart()
                                   ->where('short_code', $customAlias)
                                   ->orWhere('custom_alias', $customAlias)
                               ->groupEnd()
                               ->first();
            if ($exists) {
                return $this->respond([
                    'status'  => 'error',
                    'message' => 'Alias kustom sudah digunakan.',
                    'data'    => ['custom_alias' => 'Alias kustom sudah digunakan.']
                ], 409);
            }
            $newShortCode = $customAlias;
            $url['custom_alias'] = $customAlias;
        } elseif (empty($customAlias)) {
            $url['custom_alias'] = null;
        }

        $url['short_code'] = $newShortCode;
        $url['expires_at'] = !empty($expiresAt) ? $expiresAt : null;

        try {
            $urlModel->update($id, [
                'short_code'   => $url['short_code'],
                'custom_alias' => $url['custom_alias'],
                'expires_at'   => $url['expires_at']
            ]);

            $redis = new RedisService();
            if ($redis->isEnabled()) {
                // Hapus cache lama jika short code berubah
                if ($oldShortCode !== $newShortCode) {
                    $redis->del('url:' . $oldShortCode);
                }
                
                // Set cache baru
                $ttl = null;
                if (!empty($url['expires_at'])) {
                    $ttl = strtotime($url['expires_at']) - time();
                } else {
                    $ttl = 604800; // 7 hari
                }
                if ($ttl > 0) {
                    $redis->set('url:' . $newShortCode, json_encode($url), $ttl);
                }
            }

            $baseURL = base_url();
            return $this->respond([
                'status'  => 'success',
                'message' => 'URL berhasil diperbarui.',
                'data'    => [
                    'id'           => (int) $url['id'],
                    'short_code'   => $url['short_code'],
                    'short_url'    => rtrim($baseURL, '/') . '/' . $url['short_code'],
                    'original_url' => $url['original_url'],
                    'custom_alias' => $url['custom_alias'],
                    'clicks_count' => (int) $url['clicks_count'],
                    'expires_at'   => $url['expires_at']
                ]
            ], 200);

        } catch (Exception $e) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Gagal memperbarui URL: ' . $e->getMessage(),
                'data'    => null
            ], 500);
        }
    }

    public function delete(int $id)
    {
        $userId = $this->request->user['id'];
        $urlModel = new UrlModel();
        
        $url = $urlModel->where('id', $id)->first();
        if (!$url || $url['user_id'] != $userId) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'URL tidak ditemukan atau Anda tidak memiliki akses.',
                'data'    => null
            ], 404);
        }

        try {
            $urlModel->delete($id);

            // Invalidate Redis cache
            $redis = new RedisService();
            if ($redis->isEnabled()) {
                $redis->del('url:' . $url['short_code']);
            }

            return $this->respond([
                'status'  => 'success',
                'message' => 'URL berhasil dihapus.',
                'data'    => null
            ], 200);

        } catch (Exception $e) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Gagal menghapus URL.',
                'data'    => null
            ], 500);
        }
    }
}
