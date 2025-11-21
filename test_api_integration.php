<?php
// Archivo de prueba para la integración con la API
require_once 'backendClient.php';

function testApiIntegration()
{
    echo "<h2>Prueba de Integración con la API</h2>\n";

    $client = new BackendClient();

    // Verificar conectividad
    echo "<h3>1. Verificando conectividad con la API...</h3>\n";
    if ($client->verificarConectividad()) {
        echo "✅ API disponible<br>\n";
    } else {
        echo "❌ API no disponible - verificar que esté corriendo en http://localhost/api<br>\n";
        return;
    }

    try {
        // Crear nueva partida
        echo "<h3>2. Creando nueva partida...</h3>\n";
        $resultado = $client->crearPartida();

        echo "✅ Partida creada exitosamente:<br>\n";
        echo "- ID: " . $resultado['partidaId'] . "<br>\n";
        echo "- Pregunta: " . $resultado['pregunta']['texto'] . "<br>\n";
        echo "- Progreso: " . $resultado['progreso']['paso'] . "/" . $resultado['progreso']['total'] . "<br>\n";

        $partidaId = $resultado['partidaId'];
        $preguntaId = $resultado['pregunta']['id'];

        // Simular respuesta
        echo "<h3>3. Enviando respuesta...</h3>\n";
        $payload = [
            'preguntaId' => $preguntaId,
            'respuesta' => 'sí'
        ];

        $resultado2 = $client->responder($partidaId, $payload);

        if (isset($resultado2['resultado'])) {
            echo "✅ Juego terminado con resultado:<br>\n";
            echo "- Personaje: " . $resultado2['resultado']['personaje']['nombre'] . "<br>\n";
            echo "- Confianza: " . ($resultado2['resultado']['confianza'] * 100) . "%<br>\n";
        } else {
            echo "✅ Nueva pregunta recibida:<br>\n";
            echo "- Pregunta: " . $resultado2['pregunta']['texto'] . "<br>\n";
            echo "- Progreso: " . $resultado2['progreso']['paso'] . "/" . $resultado2['progreso']['total'] . "<br>\n";

            if (isset($resultado2['personajes_posibles'])) {
                echo "- Candidatos actuales: ";
                foreach (array_slice($resultado2['personajes_posibles'], 0, 3) as $personaje) {
                    echo $personaje['nombre'] . " (" . ($personaje['probabilidad'] * 100) . "%), ";
                }
                echo "<br>\n";
            }
        }

        // Probar reinicio
        echo "<h3>4. Probando reinicio...</h3>\n";
        $resultado3 = $client->reiniciar($partidaId);
        echo "✅ Partida reiniciada:<br>\n";
        echo "- Nueva pregunta: " . $resultado3['pregunta']['texto'] . "<br>\n";

        // Verificar finalización dentro de 12 pasos
        $pasos = 1;
        $pid = $resultado3['pregunta']['id'];
        while ($pasos <= 12) {
            $r = $client->responder($partidaId, ['preguntaId' => $pid, 'respuesta' => ($pasos % 2 === 0 ? 'no' : 'si')]);
            if (isset($r['resultado'])) {
                echo "✅ Finalizada con resultado: " . $r['resultado']['personaje']['nombre'] . "<br>\n";
                break;
            }
            $pid = $r['pregunta']['id'];
            $pasos++;
        }

    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>\n";
    }
}

// Ejecutar si se llama directamente
if (basename($_SERVER['PHP_SELF']) === 'test_api_integration.php') {
    testApiIntegration();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Prueba de Integración API</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        h2,
        h3 {
            color: #333;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }
    </style>
</head>

<body>
    <h1>Prueba de Integración del Akineitor con la API</h1>
    <p>Este archivo prueba la conectividad y funcionalidad básica de la API.</p>

    <div>
        <a href="?run=1">🔄 Ejecutar Prueba</a> |
        <a href="index.php">🏠 Volver al Akineitor</a>
    </div>

    <?php if (isset($_GET['run'])): ?>
        <hr>
        <?php testApiIntegration(); ?>
    <?php endif; ?>
</body>

</html>