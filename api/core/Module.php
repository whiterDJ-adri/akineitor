<?php

namespace Core;

interface Module
{
    /**
     * Registra controladores/servicios y rutas del módulo.
     */
    public function register(Container $c, Router $router, string $prefix): void;
}