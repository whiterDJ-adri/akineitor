<?php

namespace Modules\Algorithm;

use Core\Container;
use Core\Router;
use Core\Module as ModuleInterface;

class AlgorithmModule implements ModuleInterface
{
    public function register(Container $c, Router $router, string $prefix): void
    {
        $c->set(AlgorithmService::class, fn() => new AlgorithmService());
        $c->set(AlgorithmController::class, fn(Container $c) => new AlgorithmController($c->get(AlgorithmService::class)));

        $controller = $c->get(AlgorithmController::class);
        $router->post($prefix . '/algorithm/step', [$controller, 'step']);
    }
}