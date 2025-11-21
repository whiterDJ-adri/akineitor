<?php
function post($url, $data) {
    $opts = ['http' => ['header' => ["Content-Type: application/json", "Accept: application/json"], 'method' => 'POST', 'content' => json_encode($data), 'ignore_errors' => true]];
    $ctx = stream_context_create($opts);
    $resp = file_get_contents($url, false, $ctx);
    return json_decode($resp, true);
}

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8888') . '/projecte/akineitor/api/index.php';

$step1 = post($base . '/api/algorithm/step', []);
if (!is_array($step1) || empty($step1['partida_id'])) { echo "FALLO: step1\n"; exit(1); }
$pid = $step1['partida_id'];
$qid = $step1['pregunta_actual']['id'] ?? null;
if (!$qid) { echo "FALLO: no pregunta inicial\n"; exit(1); }

$r = post($base . '/api/algorithm/step', ['partida_id' => $pid, 'pregunta_id' => $qid, 'respuesta' => 'si']);
if (!is_array($r)) { echo "FALLO: respuesta\n"; exit(1); }

$cont = post($base . '/api/algorithm/continue', ['partida_id' => $pid]);
if (!is_array($cont) || empty($cont['pregunta_actual'])) { echo "FALLO: continuar\n"; exit(1); }
echo "OK\n";