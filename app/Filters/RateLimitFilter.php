<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class RateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $throttler = Services::throttler();
        
        // Determine rate limit key: User ID if logged in, otherwise IP
        $key = 'ip:' . $request->getIPAddress();
        if (isset($request->user) && isset($request->user['id'])) {
            $key = 'user:' . $request->user['id'];
        }

        // Limit: 60 requests per 60 seconds
        if ($throttler->check(md5($key), 60, 60) === false) {
            $response = Services::response();
            $response->setStatusCode(429);
            $response->setJSON([
                'status'  => 'error',
                'message' => 'Terlalu banyak permintaan (Rate limit exceeded). Silakan coba beberapa saat lagi.',
                'data'    => null
            ]);
            return $response;
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
