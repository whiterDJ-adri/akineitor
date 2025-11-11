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

// Definición de categorías y cómo evaluar la respuesta esperada (sí/no/no lo sé)
function get_categories()
{
    return [
        [
            'slug' => 'race_saiyan',
            'texto' => '¿Pertenece a la raza Saiyan?',
            'eval' => function ($c) {
                return (isset($c['race']) && strtolower($c['race']) === 'saiyan') ? 'sí' : 'no';
            }
        ],
        [
            'slug' => 'race_human',
            'texto' => '¿Pertenece a la raza Humana?',
            'eval' => function ($c) {
                return (isset($c['race']) && strtolower($c['race']) === 'human') ? 'sí' : 'no';
            }
        ],
        [
            'slug' => 'race_android',
            'texto' => '¿Pertenece a la raza Android?',
            'eval' => function ($c) {
                return (isset($c['race']) && strtolower($c['race']) === 'android') ? 'sí' : 'no';
            }
        ],
        [
            'slug' => 'race_frieza',
            'texto' => '¿Pertenece a la raza de Freezer?',
            'eval' => function ($c) {
                return (isset($c['race']) && strtolower($c['race']) === 'frieza race') ? 'sí' : 'no';
            }
        ],
        [
            'slug' => 'race_god',
            'texto' => '¿Pertenece a la raza Dios?',
            'eval' => function ($c) {
                return (isset($c['race']) && strtolower($c['race']) === 'god') ? 'sí' : 'no';
            }
        ],
        [
            'slug' => 'race_angel',
            'texto' => '¿Pertenece a la raza Ángel?',
            'eval' => function ($c) {
                return (isset($c['race']) && strtolower($c['race']) === 'angel') ? 'sí' : 'no';
            }
        ],
        [
            'slug' => 'race_majin',
            'texto' => '¿Pertenece a la raza Majin?',
            'eval' => function ($c) {
                return (isset($c['race']) && strtolower($c['race']) === 'majin') ? 'sí' : 'no';
            }
        ],
        [
            'slug' => 'aff_zfighter',
            'texto' => '¿Forma parte de los Guerreros Z?',
            'eval' => function ($c) {
                return (isset($c['affiliation']) && strtolower($c['affiliation']) === 'z fighter') ? 'sí' : 'no';
            }
        ],
        [
            'slug' => 'aff_army_frieza',
            'texto' => '¿Forma parte del Ejército de Freezer?',
            'eval' => function ($c) {
                return (isset($c['affiliation']) && strtolower($c['affiliation']) === 'army of frieza') ? 'sí' : 'no';
            }
        ],
        [
            'slug' => 'aff_pride_troopers',
            'texto' => '¿Forma parte de las Tropas del Orgullo?',
            'eval' => function ($c) {
                return (isset($c['affiliation']) && strtolower($c['affiliation']) === 'pride troopers') ? 'sí' : 'no';
            }
        ],
        [
            'slug' => 'universe_11',
            'texto' => '¿Pertenece al Universo 11?',
            'eval' => function ($c) {
                $aff = isset($c['affiliation']) ? strtolower($c['affiliation']) : '';
                $desc = isset($c['description']) ? mb_strtolower($c['description']) : '';
                if ($aff === 'pride troopers') return 'sí';
                if (strpos($desc, 'universo 11') !== false) return 'sí';
                return 'no';
            }
        ],
        [
            'slug' => 'season_super',
            'texto' => '¿Aparece en Dragon Ball Super?',
            'eval' => function ($c) {
                $desc = isset($c['description']) ? mb_strtolower($c['description']) : '';
                $aff = isset($c['affiliation']) ? strtolower($c['affiliation']) : '';
                $race = isset($c['race']) ? strtolower($c['race']) : '';
                if (strpos($desc, 'dragon ball super') !== false) return 'sí';
                if ($aff === 'pride troopers') return 'sí';
                if ($race === 'angel' || $race === 'god') return 'sí';
                return (strlen($desc) > 0 ? 'no' : 'no lo sé');
            }
        ],
        [
            'slug' => 'is_fusion',
            'texto' => '¿Es una fusión?',
            'eval' => function ($c) {
                $name = isset($c['name']) ? mb_strtolower($c['name']) : '';
                $desc = isset($c['description']) ? mb_strtolower($c['description']) : '';
                if (strpos($desc, 'fusión') !== false) return 'sí';
                if (in_array($name, ['gotenks', 'vegito', 'gogeta'])) return 'sí';
                return 'no';
            }
        ],
        [
            'slug' => 'is_villain',
            'texto' => '¿Es un villano?',
            'eval' => function ($c) {
                $aff = isset($c['affiliation']) ? strtolower($c['affiliation']) : '';
                $race = isset($c['race']) ? strtolower($c['race']) : '';
                if (in_array($aff, ['army of frieza', 'pride troopers'])) return 'no';
                if (in_array($race, ['majin', 'android'])) return 'sí';
                if ($aff === 'freelancer') return 'sí';
                return 'no';
            }
        ],
    ];
}

function ensure_question(MySQLConnection $db, $texto)
{
    $res = $db->query("SELECT id FROM preguntas WHERE texto_pregunta = ? LIMIT 1", [$texto]);
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) return (int)$row['id'];
    $nextRes = $db->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM preguntas");
    $nextRow = $nextRes->fetch_assoc();
    $nextId = (int)($nextRow['next_id'] ?? 1);
    $db->query("INSERT INTO preguntas (id, texto_pregunta, tipo, opciones_json) VALUES (?, ?, 'yes_no', '[\"sí\",\"no\",\"no lo sé\"]')", [
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
            // Evitar duplicados: borrar si existe y luego insertar
            $db->query("DELETE FROM personaje_pregunta WHERE personaje_id = ? AND pregunta_id = ?", [
                (string)$pc['id'],
                (string)$qid
            ]);
            $db->query(
                "INSERT INTO personaje_pregunta (personaje_id, pregunta_id, respuesta_esperada) VALUES (?, ?, ?)",
                [(string)$pc['id'], (string)$qid, $ans]
            );
            $mappedCount++;
        }
    }

    echo "Total mapeos insertados: $mappedCount\n";
    echo "Personajes sin correspondencia en data.json: $skippedNoJson\n";
    echo "Hecho.\n";
}

main();
