<?php

namespace App\Controllers;

use App\Models\UrlModel;
use App\Models\ClickLogModel;
use App\Libraries\RedisService;
use Exception;

class RedirectController extends BaseController
{
    public function redirect(string $shortCode)
    {
        $redis = new RedisService();
        $url = null;

        // 1. Cek Redis Cache (Cache-aside)
        if ($redis->isEnabled()) {
            $cached = $redis->get('url:' . $shortCode);
            if ($cached) {
                $url = json_decode($cached, true);
            }
        }

        $urlModel = new UrlModel();

        // 2. Jika Cache Miss, cek database
        if (!$url) {
            $url = $urlModel->where('short_code', $shortCode)->first();
            
            if ($url) {
                // Cache warming
                if ($redis->isEnabled()) {
                    $ttl = null;
                    if (!empty($url['expires_at'])) {
                        $ttl = strtotime($url['expires_at']) - time();
                    } else {
                        $ttl = 604800; // default cache 7 hari
                    }
                    if ($ttl > 0) {
                        $redis->set('url:' . $shortCode, json_encode($url), $ttl);
                    }
                }
            }
        }

        // 3. Jika URL tidak ditemukan
        if (!$url) {
            return $this->show404('Link tidak ditemukan atau sudah dihapus.');
        }

        // 4. Cek apakah expired
        if (!empty($url['expires_at'])) {
            if (strtotime($url['expires_at']) < time()) {
                // Hapus cache jika expired
                if ($redis->isEnabled()) {
                    $redis->del('url:' . $shortCode);
                }
                return $this->show404('Link ini telah kedaluwarsa.');
            }
        }

        // 5. Log click secara async/cepat
        $this->logClick($url, $redis);

        // 6. Redirect
        return redirect()->to($url['original_url']);
    }

    private function logClick(array $url, RedisService $redis)
    {
        $ip = $this->request->getIPAddress();
        $ua = $this->request->getUserAgent()->getAgentString();
        $referrer = $this->request->getUserAgent()->getReferrer();
        
        if ($redis->isEnabled()) {
            // A. Gunakan Redis queue & Redis click counter (Distributed concept)
            $redis->incr('url:clicks_count:' . $url['short_code']);
            
            $logData = [
                'url_id'     => $url['id'],
                'short_code' => $url['short_code'],
                'ip_address' => $ip,
                'user_agent' => $ua,
                'referrer'   => !empty($referrer) ? $referrer : null,
                'clicked_at' => date('Y-m-d H:i:s')
            ];
            $redis->lpush('click_logs_queue', json_encode($logData));
        } else {
            // B. Direct database fallback jika Redis mati
            try {
                // Increment click counter
                $urlModel = new UrlModel();
                $urlModel->where('id', $url['id'])->increment('clicks_count');

                // Simpan detail log
                $clickLogModel = new ClickLogModel();
                $clickLogModel->insert([
                    'url_id'     => $url['id'],
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                    'referrer'   => !empty($referrer) ? $referrer : null,
                    'clicked_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                log_message('error', 'Gagal mencatat click log ke database: ' . $e->getMessage());
            }
        }
    }

    private function show404(string $message)
    {
        if ($this->request->isAJAX() || str_contains($this->request->getHeaderLine('Accept'), 'application/json')) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => $message,
                'data'    => null
            ]);
        }

        return $this->response->setStatusCode(404)->setBody("
            <!DOCTYPE html>
            <html lang='id'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Link Tidak Ditemukan</title>
                <script src='https://cdn.tailwindcss.com'></script>
            </head>
            <body class='bg-gray-900 text-white flex items-center justify-center min-h-screen'>
                <div class='text-center p-8 bg-gray-800 rounded-lg shadow-xl max-w-md w-full border border-gray-700'>
                    <div class='text-red-500 text-6xl mb-4'>⚠️</div>
                    <h1 class='text-2xl font-bold mb-2'>Tautan Tidak Aktif</h1>
                    <p class='text-gray-400 mb-6'>$message</p>
                    <a href='/app/index.html' class='bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2 px-4 rounded-md transition duration-200'>
                        Buat Short Link Baru
                    </a>
                </div>
            </body>
            </html>
        ");
    }
}
