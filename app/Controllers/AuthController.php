<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;
use Firebase\JWT\JWT;
use Exception;

class AuthController extends BaseController
{
    use ResponseTrait;

    public function register()
    {
        $rules = [
            'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'email'    => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[6]'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Validasi gagal.',
                'data'    => $this->validator->getErrors()
            ], 400);
        }

        $userModel = new UserModel();
        
        $data = [
            'username' => $this->request->getVar('username'),
            'email'    => $this->request->getVar('email'),
            'password' => $this->request->getVar('password'),
        ];

        try {
            $userModel->insert($data);
            return $this->respond([
                'status'  => 'success',
                'message' => 'Registrasi berhasil. Silakan login.',
                'data'    => null
            ], 201);
        } catch (Exception $e) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Registrasi gagal, coba lagi nanti.',
                'data'    => null
            ], 500);
        }
    }

    public function login()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Validasi gagal.',
                'data'    => $this->validator->getErrors()
            ], 400);
        }

        $userModel = new UserModel();
        $user = $userModel->where('email', $this->request->getVar('email'))->first();

        if (!$user || !password_verify($this->request->getVar('password'), $user['password'])) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Email atau password salah.',
                'data'    => null
            ], 401);
        }

        try {
            $key = env('jwt.secret', 'super-secret-key-change-in-production-12345');
            $payload = [
                'iss'  => 'url-shortener-api',
                'aud'  => 'url-shortener-client',
                'iat'  => time(),
                'nbf'  => time(),
                'exp'  => time() + 86400, // Valid for 24 hours
                'data' => [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'email'    => $user['email']
                ]
            ];

            $token = JWT::encode($payload, $key, 'HS256');

            return $this->respond([
                'status'  => 'success',
                'message' => 'Login berhasil.',
                'data'    => [
                    'token' => $token,
                    'user'  => [
                        'id'       => $user['id'],
                        'username' => $user['username'],
                        'email'    => $user['email']
                    ]
                ]
            ], 200);
        } catch (Exception $e) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Gagal membuat token login.',
                'data'    => null
            ], 500);
        }
    }
}
