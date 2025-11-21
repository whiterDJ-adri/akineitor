<?php
require_once __DIR__ . '/../models/MySQLConnection.php';
require_once __DIR__ . '/../modules/algorithm/AlgorithmService.php';

use Modules\Algorithm\AlgorithmService;

function assertTrue($cond, $msg) {
    if (!$cond) {
        echo "[FALLO] $msg\n";
        exit(1);
    } else {
        echo "[OK] $msg\n";
    }
}

echo "== Prueba: Sin sistema de categorías ==\n";

$svc = new AlgorithmService();

assertTrue(!method_exists($svc, 'getCategoryQuestionTexts'), 'No existe getCategoryQuestionTexts');
assertTrue(!method_exists($svc, 'evalCategoryAnswer'), 'No existe evalCategoryAnswer');
assertTrue(!method_exists($svc, 'loadJsonCharacters'), 'No existe loadJsonCharacters');
assertTrue(!method_exists($svc, 'normalizeName'), 'No existe normalizeName');
assertTrue(!method_exists($svc, 'domainRelevanceBoost'), 'No existe domainRelevanceBoost');
assertTrue(!method_exists($svc, 'filterContradictoryOrRedundant'), 'No existe filterContradictoryOrRedundant');
assertTrue(!method_exists($svc, 'mappingTopAnswerForAsked'), 'No existe mappingTopAnswerForAsked');

$preguntas = $svc->getAllPreguntas();
assertTrue(is_array($preguntas) && count($preguntas) >= 0, 'getAllPreguntas devuelve array');

$personajes = $svc->getAllPersonajes();
assertTrue(is_array($personajes) && count($personajes) >= 0, 'getAllPersonajes devuelve array');

$map = $svc->getMapping();
assertTrue(is_array($map), 'getMapping devuelve array');

echo "== Prueba: Paso API ==\n";
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8888';
$base = $protocol . '://' . $host . '/projecte/akineitor/api/index.php';

$opts = ['http' => ['header' => ["Content-Type: application/json", "Accept: application/json"], 'method' => 'POST', 'content' => json_encode([]), 'ignore_errors' => true]];
$ctx = stream_context_create($opts);
$resp = file_get_contents($base . '/api/algorithm/step', false, $ctx);
$dec = json_decode($resp, true);
assertTrue(is_array($dec), 'Respuesta JSON válida');
assertTrue(array_key_exists('partida_id', $dec), 'Crea partida_id');
assertTrue(array_key_exists('preguntas_respondidas', $dec), 'Incluye preguntas_respondidas');

echo "== OK: Sin categorías y API funcional ==\n";