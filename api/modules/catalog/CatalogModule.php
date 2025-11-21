<?php

namespace Modules\Catalog;

use Core\Container;
use Core\Router;
use Core\Module as ModuleInterface;

class CatalogModule implements ModuleInterface
{
    public function register(Container $c, Router $router, string $prefix): void
    {
        $c->set(CatalogService::class, fn() => new CatalogService());
        $c->set(CatalogController::class, fn(Container $c) => new CatalogController($c->get(CatalogService::class)));
        $controller = $c->get(CatalogController::class);
        $router->post($prefix . '/catalog/characters/create', [$controller, 'create']);
        $router->post($prefix . '/catalog/characters/update', [$controller, 'update']);
        $router->post($prefix . '/catalog/characters/delete', [$controller, 'delete']);
        $router->get($prefix . '/catalog/characters/list', [$controller, 'list']);
    }
}