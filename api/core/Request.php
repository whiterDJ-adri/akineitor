<?php

namespace Core;

class Request
{
    public string $method;
    public string $path;
    public array $headers;
    public array $query;
    public array $body;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $rawPath = parse_url($uri, PHP_URL_PATH) ?? '/';
        // Normaliza la ruta eliminando prefijos del directorio del script y /index.php
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = rtrim(dirname($scriptName), '/');
        $path = $rawPath;
        if ($scriptDir && strpos($path, $scriptDir) === 0) {
            $path = substr($path, strlen($scriptDir));
        }
        if (strpos($path, '/index.php') === 0) {
            $path = substr($path, strlen('/index.php'));
        }
        $this->path = $path === '' ? '/' : $path;
        $this->headers = function_exists('getallheaders') ? getallheaders() : [];
        $this->query = $_GET ?? [];

        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        $this->body = is_array($json) ? $json : $_POST;
    }
}