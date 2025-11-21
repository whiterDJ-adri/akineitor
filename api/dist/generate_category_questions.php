<?php
// Genera preguntas por categorías (razas, afiliaciones, temporadas) y mapea respuestas esperadas
// en la tabla personaje_pregunta, reutilizando preguntas si ya existen.

require_once __DIR__ . '/../models/MySQLConnection.php';

function normalize_name($name)
{
    $n = mb_strtolower(trim($name));
    $n = preg_replace('/\s+/', ' ', $n);
    $n = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $n);
    return $n;
}

function load_json_characters($jsonPath)
{
    if (!file_exists($jsonPath)) {
        throw new Exception("No se encontró data.json en: $jsonPath");
    }
    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new Exception("data.json no es válido");
    }
    $byName = [];
    foreach ($data as $c) {
        if (!isset($c['name'])) continue;
        $byName[normalize_name($c['name'])] = $c;
    }
    return $byName;
}

function get_db_personajes(MySQLConnection $db)
{
    $res = $db->query("SELECT id, nombre FROM personajes");
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'nombre_norm' => normalize_name($row['nombre'])
        ];
    }
    return $out;
}


function ensure_question(MySQLConnection $db, $texto)
{
    $res = $db->query("SELECT id FROM preguntas WHERE texto_pregunta = ? LIMIT 1", [$texto]);
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) return (int)$row['id'];
    $nextRes = $db->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM preguntas");
    $nextRow = $nextRes->fetch_assoc();
    $nextId = (int)($nextRow['next_id'] ?? 1);
    $db->query("INSERT INTO preguntas (id, texto_pregunta, tipo, category) VALUES (?, ?, 'yes_no', '')", [
        (string)$nextId,
        $texto
    ]);
    return $nextId;
}

function main()
{
    $db = new MySQLConnection();
    $jsonPath = __DIR__ . '/../models/data.json';
    $jsonChars = load_json_characters($jsonPath);
    $dbChars = get_db_personajes($db);
    $cats = get_categories();

    $questionIds = [];
    foreach ($cats as $cat) {
        $qid = ensure_question($db, $cat['texto']);
        $questionIds[$cat['slug']] = $qid;
        echo "Pregunta creada/reciclada: {$cat['texto']} -> ID $qid\n";
    }

    $mappedCount = 0;
    $skippedNoJson = 0;
    foreach ($dbChars as $pc) {
        $norm = $pc['nombre_norm'];
        $json = $jsonChars[$norm] ?? null;
        if (!$json) {
            $skippedNoJson++;
        }
        foreach ($cats as $cat) {
            $qid = $questionIds[$cat['slug']];
            $ans = 'no lo sé';
            if ($json) {
                try {
                    $ans = $cat['eval']($json);
                    if (!in_array($ans, ['sí', 'no', 'no lo sé'])) $ans = 'no lo sé';
                } catch (Throwable $e) {
                    $ans = 'no lo sé';
                }
            }

            $mappedCount++;
        }
    }

    echo "Total mapeos insertados: $mappedCount\n";
    echo "Personajes sin correspondencia en data.json: $skippedNoJson\n";
    echo "Hecho.\n";
}

main();
