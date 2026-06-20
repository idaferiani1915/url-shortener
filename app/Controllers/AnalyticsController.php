<?php

namespace App\Controllers;

use App\Models\UrlModel;
use App\Models\ClickLogModel;
use App\Libraries\RedisService;
use CodeIgniter\API\ResponseTrait;

class AnalyticsController extends BaseController
{
    use ResponseTrait;

    public function stats(int $id)
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

        // Get click count (real-time check in Redis first to be accurate)
        $clicksCount = (int) $url['clicks_count'];
        
        $redis = new RedisService();
        if ($redis->isEnabled()) {
            $cachedClicks = $redis->get('url:clicks_count:' . $url['short_code']);
            if ($cachedClicks !== null && (int) $cachedClicks > $clicksCount) {
                $clicksCount = (int) $cachedClicks;
            }
        }

        // Fetch click logs from DB (limit to 100 most recent clicks for efficiency)
        $clickLogModel = new ClickLogModel();
        $logs = $clickLogModel->where('url_id', $id)
                              ->orderBy('clicked_at', 'DESC')
                              ->limit(100)
                              ->findAll();

        return $this->respond([
            'status'  => 'success',
            'message' => 'Statistik URL berhasil dimuat.',
            'data'    => [
                'url' => [
                    'id'           => (int) $url['id'],
                    'short_code'   => $url['short_code'],
                    'original_url' => $url['original_url'],
                    'custom_alias' => $url['custom_alias'],
                    'clicks_count' => $clicksCount,
                    'expires_at'   => $url['expires_at'],
                    'created_at'   => $url['created_at']
                ],
                'click_logs' => $logs
            ]
        ], 200);
    }
}
