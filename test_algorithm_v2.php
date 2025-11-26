<?php

// Mock de MySQLConnection para pruebas sin BD real (o con BD real si disponible)
// Para simplificar, usaremos la conexión real pero con cuidado.
require_once __DIR__ . '/api/models/MySQLConnection.php';
require_once __DIR__ . '/api/modules/algorithm/AlgorithmService.php';

use Modules\Algorithm\AlgorithmService;

echo "Iniciando prueba del algoritmo...\n";

try {
    $service = new AlgorithmService();
    
    // 1. Crear partida
    echo "1. Creando partida...\n";
    $game = $service->createNewGame();
    $partidaId = $game['partidaId'];
    echo "Partida ID: $partidaId\n";
    
    // Simular juego pensando en "Goku" (ID 1)
    // Datos de Goku: Saiyan, Male, Z Fighter, etc.
    $targetName = "Goku";
    echo "Objetivo: $targetName\n";

    $maxSteps = 20;
    $currentQ = $game['pregunta'];
    
    for ($i = 0; $i < $maxSteps; $i++) {
        if (!$currentQ) break;
        
        $qText = $currentQ['texto'];
        $qId = $currentQ['id'];
        
        // Determinar respuesta automática para Goku usando el mismo mapper (Simulación de usuario perfecto)
        // En la realidad, el usuario puede fallar, pero queremos probar el motor de inferencia.
        
        // Cargar datos de Goku (ID 1) manualmente o hardcoded para el test
        $gokuData = [
            'id' => 1,
            'name' => 'Goku',
            'race' => 'Saiyan',
            'gender' => 'Male',
            'affiliation' => 'Z Fighter',
            'description' => 'El protagonista de la serie...',
            'ki' => '60.000.000',
            'maxKi' => '90 Septillion',
            'image' => '...'
        ];

        require_once __DIR__ . '/api/modules/algorithm/AttributeMapper.php';
        $answer = \Modules\Algorithm\AttributeMapper::getAnswer($gokuData, $qText);
        
        echo "Paso " . ($i+1) . ": ¿$qText? -> $answer\n";
        
        $resp = $service->responder((string)$partidaId, [
            'preguntaId' => $qId,
            'respuesta' => $answer
        ]);
        
        if (isset($resp['resultado'])) {
            echo "\n¡Juego Terminado!\n";
            $res = $resp['resultado'];
            echo "Personaje: " . $res['personaje']['nombre'] . " (Confianza: " . $res['confianza'] . "%)\n";
            
            if ($res['personaje']['nombre'] === $targetName) {
                echo "RESULTADO: ÉXITO (Adivinó correctamente)\n";
            } else {
                echo "RESULTADO: FALLO (Adivinó a " . $res['personaje']['nombre'] . ")\n";
            }
            break;
        }
        
        $currentQ = $resp['pregunta'];
        echo "   -> Confianza actual top: " . ($resp['confianza'] ?? '?') . "%\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
