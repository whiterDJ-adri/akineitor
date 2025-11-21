<?php
function post($url, $data) {
    $opts = ['http' => ['header' => ["Content-Type: application/json", "Accept: application/json"], 'method' => 'POST', 'content' => json_encode($data), 'ignore_errors' => true]];
    $ctx = stream_context_create($opts);
    $resp = file_get_contents($url, false, $ctx);
    return json_decode($resp, true);
}

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8888') . '/projecte/akineitor/api/index.php/api/algorithm/step';

echo "== Hint Bulma ==\n";
$r1 = post($base, ['nombre_hint' => 'Bulma']);
echo json_encode(['final' => $r1['es_final'] ?? null, 'mensaje' => $r1['mensaje'] ?? null, 'top' => $r1['personajes_posibles'][0]['nombre'] ?? null], JSON_UNESCAPED_UNICODE) . "\n";

echo "== Hint GOKU ==\n";
$r2 = post($base, ['nombre_hint' => 'GOKU']);
echo json_encode(['final' => $r2['es_final'] ?? null, 'mensaje' => $r2['mensaje'] ?? null, 'top' => $r2['personajes_posibles'][0]['nombre'] ?? null], JSON_UNESCAPED_UNICODE) . "\n";

echo "== Hint Búlma (acentos) ==\n";
$r3 = post($base, ['nombre_hint' => 'Búlma']);
echo json_encode(['final' => $r3['es_final'] ?? null, 'mensaje' => $r3['mensaje'] ?? null, 'top' => $r3['personajes_posibles'][0]['nombre'] ?? null], JSON_UNESCAPED_UNICODE) . "\n";