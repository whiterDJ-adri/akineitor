<?php

namespace Modules\Auth;

class AuthService
{
    public function validate(string $email, string $password): ?array
    {
        // TODO: integrar base de datos real
        if ($email === 'goku@dbz.com' && $password === 'kamehame') {
            return ['id' => 1, 'name' => 'Goku', 'email' => $email];
        }
        return null;
    }
}
