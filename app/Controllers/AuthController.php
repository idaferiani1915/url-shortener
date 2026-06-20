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

    public function forgotPassword()
    {
        $rules = [
            'email' => 'required|valid_email'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Validasi gagal.',
                'data'    => $this->validator->getErrors()
            ], 400);
        }

        $emailInput = $this->request->getVar('email');
        $userModel = new UserModel();
        $user = $userModel->where('email', $emailInput)->first();

        // Selalu return respon sukses yang sama demi keamanan (mencegah enumerasi akun)
        $successResponse = [
            'status'  => 'success',
            'message' => 'Jika email Anda terdaftar, tautan reset kata sandi telah dikirim.',
            'data'    => null
        ];

        if (!$user) {
            return $this->respond($successResponse, 200);
        }

        try {
            // Generate secure random token
            $token = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $token);
            $resetExpiresAt = date('Y-m-d H:i:s', time() + 900); // Valid 15 menit

            // Simpan token ter-hash ke DB
            $userModel->update($user['id'], [
                'reset_token'            => $hashedToken,
                'reset_token_expires_at' => $resetExpiresAt
            ]);

            // Buat link reset password
            $resetLink = base_url("app/reset-password.html?token=$token");

            // Format email HTML
            $subject = 'Atur Ulang Kata Sandi Anda - Gazin';
            $message = "
                <html>
                <body style='font-family: Arial, sans-serif; background-color: #0f172a; color: #f1f5f9; padding: 20px; margin: 0;'>
                    <div style='max-width: 500px; margin: 20px auto; background-color: #1e293b; border: 1px solid #334155; padding: 30px; border-radius: 12px;'>
                        <h2 style='color: #3b82f6; font-size: 24px; font-weight: bold; margin-bottom: 20px;'>Gazin URL Shortener</h2>
                        <p style='color: #cbd5e1; font-size: 15px; line-height: 1.5;'>Halo,</p>
                        <p style='color: #cbd5e1; font-size: 15px; line-height: 1.5;'>Kami menerima permintaan untuk mereset kata sandi akun Anda. Silakan klik tombol di bawah ini untuk mengatur ulang kata sandi Anda:</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='{$resetLink}' style='background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; display: inline-block;'>Atur Ulang Kata Sandi</a>
                        </div>
                        <p style='font-size: 12px; color: #64748b; line-height: 1.4; border-t: 1px solid #334155; pt: 15px; margin-top: 20px;'>Tautan ini hanya berlaku selama 15 menit. Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini.</p>
                    </div>
                </body>
                </html>
            ";

            // Gunakan antrean asinkronus Redis jika aktif
            $redis = new \App\Libraries\RedisService();
            if ($redis->isEnabled()) {
                $emailData = [
                    'to'      => $user['email'],
                    'subject' => $subject,
                    'message' => $message
                ];
                $redis->lpush('email_queue', json_encode($emailData));
            } else {
                // Fallback kirim langsung jika Redis mati
                $email = \Config\Services::email();
                $email->setTo($user['email']);
                $email->setSubject($subject);
                $email->setMessage($message);
                $email->send();
            }

            return $this->respond($successResponse, 200);

        } catch (Exception $e) {
            log_message('error', 'Gagal memproses forgot password: ' . $e->getMessage());
            return $this->respond([
                'status'  => 'error',
                'message' => 'Gagal memproses permintaan reset password.',
                'data'    => null
            ], 500);
        }
    }

    public function resetPassword()
    {
        $rules = [
            'token'        => 'required',
            'new_password' => 'required|min_length[6]'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Validasi gagal.',
                'data'    => $this->validator->getErrors()
            ], 400);
        }

        $token = $this->request->getVar('token');
        $newPassword = $this->request->getVar('new_password');
        
        $hashedToken = hash('sha256', $token);
        $userModel = new UserModel();

        // Cari user dengan token ter-hash dan belum expired
        $user = $userModel->where('reset_token', $hashedToken)
                          ->where('reset_token_expires_at >=', date('Y-m-d H:i:s'))
                          ->first();

        if (!$user) {
            return $this->respond([
                'status'  => 'error',
                'message' => 'Tautan reset kata sandi tidak valid atau telah kedaluwarsa.',
                'data'    => null
            ], 400);
        }

        try {
            // Update password & hapus token agar tidak bisa dipakai ulang
            $userModel->update($user['id'], [
                'password'               => $newPassword,
                'reset_token'            => null,
                'reset_token_expires_at' => null
            ]);

            return $this->respond([
                'status'  => 'success',
                'message' => 'Kata sandi berhasil diatur ulang. Silakan login kembali.',
                'data'    => null
            ], 200);

        } catch (Exception $e) {
            log_message('error', 'Gagal reset password: ' . $e->getMessage());
            return $this->respond([
                'status'  => 'error',
                'message' => 'Gagal mereset kata sandi.',
                'data'    => null
            ], 500);
        }
    }
}
