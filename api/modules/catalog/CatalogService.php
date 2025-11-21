<?php

namespace Modules\Catalog;

class CatalogService
{
    private \MySQLConnection $db;

    public function __construct()
    {
        $this->db = new \MySQLConnection();
    }

    public function createCharacter(string $nombre, ?string $descripcion = null, ?string $imagenUrl = null): array
    {
        $this->db->query(
            "INSERT INTO personajes (nombre, descripcion, imagen_url) VALUES (?, ?, ?)",
            [$nombre, $descripcion ?? '', $imagenUrl ?? '']
        );
        $rid = $this->db->query("SELECT LAST_INSERT_ID() AS id");
        $row = $rid->fetch_assoc();
        $id = (int)($row['id'] ?? 0);
        return ['id' => $id, 'nombre' => $nombre, 'descripcion' => $descripcion, 'imagen_url' => $imagenUrl];
    }

    public function updateCharacter(int $id, array $fields): bool
    {
        $set = [];
        $params = [];
        foreach (['nombre','descripcion','imagen_url'] as $k) {
            if (array_key_exists($k, $fields)) {
                $set[] = "$k = ?";
                $params[] = (string)$fields[$k];
            }
        }
        if (empty($set)) return false;
        $params[] = (string)$id;
        $sql = "UPDATE personajes SET " . implode(', ', $set) . " WHERE id = ?";
        $this->db->query($sql, $params);
        return true;
    }

    public function deleteCharacter(int $id): bool
    {
        $this->db->query("DELETE FROM personajes WHERE id = ?", [(string)$id]);
        return true;
    }

    public function listCharacters(): array
    {
        $res = $this->db->query("SELECT id, nombre, descripcion, imagen_url FROM personajes ORDER BY nombre ASC");
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
        }
        return $out;
    }
}