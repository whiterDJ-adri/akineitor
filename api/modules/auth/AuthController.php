<?php

namespace Modules\Auth;

use Core\Request;
use Core\Response;

class AuthController
{
    public function __construct(private AuthService $service)
    {
    }

    public function login(Request $req, Response $res): void
    {
        $email = $req->body['email'] ?? '';
        $password = $req->body['password'] ?? '';
        $user = $this->service->validate($email, $password);
        if (!$user) {
            $res::json(['error' => 'Credenciales inválidas'], 401);
            return;
        }
        $token = base64_encode("{$user['id']}|{$user['email']}|" . time());
        $res::json(['user' => $user, 'token' => $token]);
    }

    public function me(Request $req, Response $res): void
    {
        $res::json(['user' => ['id' => 1, 'name' => 'Goku', 'email' => 'goku@dbz.com']]);
    }
}