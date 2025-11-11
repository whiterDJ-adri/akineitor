<?php

namespace Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function options(string $path, callable $handler): void
    {
        $this->routes['OPTIONS'][$path] = $handler;
    }

    public function dispatch(Request $req): void
    {
        $methodRoutes = $this->routes[$req->method] ?? [];
        $handler = $methodRoutes[$req->path] ?? null;
        if (!$handler) {
            Response::json(['error' => 'Ruta no encontrada', 'path' => $req->path], 404);
            return;
        }
        $handler($req, new Response());
    }
}
