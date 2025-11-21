<?php

namespace Modules\Catalog;

use Core\Request;
use Core\Response;

class CatalogController
{
    public function __construct(private CatalogService $service) {}

    public function create(Request $req, Response $res): void
    {
        $nombre = trim((string)($req->body['nombre'] ?? ''));
        $descripcion = $req->body['descripcion'] ?? null;
        $imagen = $req->body['imagen_url'] ?? null;
        if ($nombre === '') {
            $res::json(['error' => 'Nombre requerido'], 400);
            return;
        }
        $created = $this->service->createCharacter($nombre, $descripcion, $imagen);
        $res::json(['personaje' => $created]);
    }

    public function update(Request $req, Response $res): void
    {
        $idRaw = $req->body['id'] ?? null;
        if ($idRaw === null) {
            $res::json(['error' => 'ID requerido'], 400);
            return;
        }
        $ok = $this->service->updateCharacter((int)$idRaw, $req->body);
        $res::json(['ok' => $ok]);
    }

    public function delete(Request $req, Response $res): void
    {
        $idRaw = $req->body['id'] ?? null;
        if ($idRaw === null) {
            $res::json(['error' => 'ID requerido'], 400);
            return;
        }
        $ok = $this->service->deleteCharacter((int)$idRaw);
        $res::json(['ok' => $ok]);
    }

    public function list(Request $req, Response $res): void
    {
        $list = $this->service->listCharacters();
        $res::json(['personajes' => $list]);
    }
}