<?php

require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Module.php';
require_once __DIR__ . '/core/Container.php';
require_once __DIR__ . '/core/Cors.php';
require_once __DIR__ . '/models/MySQLConnection.php';
require_once __DIR__ . '/modules/health/HealthController.php';
require_once __DIR__ . '/modules/health/HealthModule.php';
require_once __DIR__ . '/modules/auth/AuthService.php';
require_once __DIR__ . '/modules/auth/AuthController.php';
require_once __DIR__ . '/modules/auth/AuthModule.php';
require_once __DIR__ . '/modules/algorithm/AlgorithmService.php';
require_once __DIR__ . '/modules/algorithm/AlgorithmController.php';
require_once __DIR__ . '/modules/algorithm/AlgorithmModule.php';

use Core\Request;
use Core\Response;
use Core\Router;
use Core\Container;
use Core\Cors;
use Modules\Health\HealthController;
use Modules\Health\HealthModule;
use Modules\Auth\AuthService;
use Modules\Auth\AuthController;
use Modules\Auth\AuthModule;
use Modules\Algorithm\AlgorithmModule;

Cors::apply();

$router = new Router();
$container = new Container();
$req = new Request();

// Preflight OPTIONS para cualquier ruta /api
$router->options('/api', function (Request $req) {
    http_response_code(204);
});

// Registro de módulos al estilo NestJS
$prefix = '/api';
(new HealthModule())->register($container, $router, $prefix);
(new AuthModule())->register($container, $router, $prefix);
(new AlgorithmModule())->register($container, $router, $prefix);

$router->dispatch($req);
