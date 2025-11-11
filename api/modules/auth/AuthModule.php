<?php

namespace Modules\Auth;

use Core\Container;
use Core\Router;
use Core\Module as ModuleInterface;

class AuthModule implements ModuleInterface
{
    public function register(Container $c, Router $router, string $prefix): void
    {
        $c->set(AuthService::class, fn() => new AuthService());
        $c->set(AuthController::class, fn(Container $c) => new AuthController($c->get(AuthService::class)));

        $controller = $c->get(AuthController::class);
        $router->post($prefix . '/auth/login', [$controller, 'login']);
        $router->get($prefix . '/auth/me', [$controller, 'me']);
    }
}