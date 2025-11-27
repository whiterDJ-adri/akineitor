<?php
require_once __DIR__ . '/api/models/MySQLConnection.php';
require_once __DIR__ . '/api/modules/algorithm/AttributeMapper.php';

use Modules\Algorithm\AttributeMapper;

$db = new MySQLConnection();

// 1. Find Characters
echo "--- Characters ---\n";
$res = $db->query("SELECT id, nombre, race, gender, affiliation FROM personajes WHERE nombre LIKE '%Bulma%' OR nombre LIKE '%Trunks%'");
$chars = [];
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']}, Name: {$row['nombre']}, Race: {$row['race']}, Gender: {$row['gender']}, Affiliation: {$row['affiliation']}\n";
    $chars[$row['id']] = $row;
}

// 2. Find Fusion Question
echo "\n--- Questions ---\n";
$res = $db->query("SELECT id, texto_pregunta FROM preguntas WHERE texto_pregunta LIKE '%fusion%' OR texto_pregunta LIKE '%fusión%'");
$questions = [];
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']}, Text: {$row['texto_pregunta']}\n";
    $questions[$row['id']] = $row;
}

// 3. Check Learned Answers
echo "\n--- Learned Answers (personaje_pregunta) ---\n";
if (!empty($chars) && !empty($questions)) {
    $charIds = implode(',', array_keys($chars));
    $qIds = implode(',', array_keys($questions));
    $sql = "SELECT * FROM personaje_pregunta WHERE personaje_id IN ($charIds) AND pregunta_id IN ($qIds)";
    $res = $db->query($sql);
    while ($row = $res->fetch_assoc()) {
        echo "CharID: {$row['personaje_id']} -> QID: {$row['pregunta_id']} = {$row['respuesta_esperada']}\n";
    }
}

// 4. Check AttributeMapper Logic
echo "\n--- AttributeMapper Logic ---\n";
foreach ($chars as $char) {
    foreach ($questions as $q) {
        $ans = AttributeMapper::getAnswer($char, $q['texto_pregunta']);
        echo "Mapper: {$char['nombre']} + '{$q['texto_pregunta']}' => $ans\n";
    }
}
