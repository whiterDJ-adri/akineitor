<?php

namespace Modules\Health;

use Core\Container;
use Core\Router;
use Core\Module as ModuleInterface;

class HealthModule implements ModuleInterface
{
    public function register(Container $c, Router $router, string $prefix): void
    {
        $c->set(HealthController::class, fn() => new HealthController());
        $router->get($prefix . '/health', [$c->get(HealthController::class), 'status']);
    }
}