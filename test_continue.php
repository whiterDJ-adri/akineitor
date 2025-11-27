<?php
// Load dependencies manually to avoid executing api/index.php router
require_once __DIR__ . '/api/models/MySQLConnection.php';
require_once __DIR__ . '/api/modules/algorithm/AttributeMapper.php';
require_once __DIR__ . '/api/modules/algorithm/AlgorithmService.php';

use Modules\Algorithm\AlgorithmService;

echo "Testing Continue Game Logic...\n";

try {
    $service = new AlgorithmService();

    // 1. Create Game
    echo "1. Creating new game...\n";
    $game = $service->createNewGame();
    $partidaId = $game['partidaId'];
    echo "Game ID: $partidaId\n";

    // 2. Simulate answering to get a result
    echo "2. Playing turns...\n";
    $reachedResult = false;
    for ($i = 0; $i < 25; $i++) {
        // Answer 'sí' to everything to force a path
        $res = $service->responder((string) $partidaId, ['respuesta' => 'sí']);
        if (isset($res['resultado'])) {
            echo "Reached result at step " . ($i + 1) . "\n";
            print_r($res['resultado']);
            $reachedResult = true;
            break;
        }
    }

    if (!$reachedResult) {
        echo "WARNING: Did not reach result naturally. Forcing state for testing.\n";
        // We can't easily force state without direct DB access or reflection, 
        // but let's assume the loop above works for most cases or just try continue anyway
        // (AlgorithmService might throw if game is not finished, or maybe not?)
        // Actually continueGame checks if game exists. It doesn't strictly require 'completed' status in my code,
        // but it does logic based on probabilities.
    }

    // 3. Test Continue
    echo "\n3. Testing Continue...\n";
    $continueRes = $service->continueGame((string) $partidaId);
    echo "Continue Result:\n";
    print_r($continueRes);

    if (isset($continueRes['pregunta'])) {
        echo "SUCCESS: Game continued with a new question.\n";
    } else {
        echo "FAILURE: Game did not return a question.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
