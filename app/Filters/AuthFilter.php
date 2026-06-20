<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getServer('HTTP_AUTHORIZATION');
        
        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Token Authorization tidak ditemukan.');
        }

        $arr = explode(" ", $authHeader);
        $token = isset($arr[1]) ? $arr[1] : $arr[0];

        if (empty($token)) {
            return $this->unauthorizedResponse('Format token tidak valid.');
        }

        try {
            $key = env('jwt.secret', 'super-secret-key-change-in-production-12345');
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            
            // Inject decoded user data into the request
            $request->user = (array) $decoded->data;
            
        } catch (Exception $e) {
            return $this->unauthorizedResponse('Token tidak valid atau telah kedaluwarsa: ' . $e->getMessage());
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }

    private function unauthorizedResponse(string $message)
    {
        $response = service('response');
        $response->setStatusCode(401);
        $response->setJSON([
            'status'  => 'error',
            'message' => $message,
            'data'    => null
        ]);
        return $response;
    }
}
