<?php
// Script de diagnóstico simple
echo "=== Diagnóstico de Conectividad API ===\n";

// 1. Verificar que la API responda
echo "1. Probando conectividad básica...\n";
$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'ignore_errors' => true
    ]
]);

$url = 'http://localhost/api/health';
$result = @file_get_contents($url, false, $context);

if ($result === false) {
    echo " No se puede conectar a $url\n";
    echo "Posibles causas:\n";
    echo "- XAMPP no está corriendo\n";
    echo "- El archivo api/index.php no existe\n";
    echo "- Hay errores en el código de la API\n";
} else {
    echo "API responde: $result\n";
}

// 2. Verificar que existe el archivo de la API
echo "\n2. Verificando archivos de la API...\n";
$apiFile = __DIR__ . '/api/index.php';
if (file_exists($apiFile)) {
    echo "Archivo api/index.php existe\n";
} else {
    echo "Archivo api/index.php NO existe\n";
}

// 3. Probar BackendClient directamente
echo "\n3. Probando BackendClient...\n";
try {
    require_once 'backendClient.php';
    $client = new BackendClient();

    echo "BackendClient cargado correctamente\n";

    // Probar conectividad
    if ($client->verificarConectividad()) {
        echo "verificarConectividad() funciona\n";

        // Probar crear partida
        echo "4. Probando crear partida...\n";
        $resultado = $client->crearPartida();
        echo "crearPartida() funciona\n";
        echo "Resultado: " . json_encode($resultado, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "verificarConectividad() falló\n";
    }

} catch (Exception $e) {
    echo " Error en BackendClient: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>