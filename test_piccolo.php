try {
    $service = new AlgorithmService();
    
    // 1. Get Piccolo's ID
    $db = new MySQLConnection();
    $result = $db->query("SELECT id, nombre FROM personajes WHERE nombre LIKE '%Piccolo%'");
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "Found Piccolo: ID = {$row['id']}, Name = {$row['nombre']}\n\n";
        $piccoloId = $row['id'];
    } else {
        echo "Piccolo not found in database!\n";
        exit(1);
    }
    
    // 2. Get Piccolo's expected answers
    $answers = $db->query("SELECT pregunta_id, respuesta_esperada FROM personaje_pregunta WHERE personaje_id = ?", [$piccoloId]);
    $piccoloAnswers = [];
    while ($row = $answers->fetch_assoc()) {
        $piccoloAnswers[$row['pregunta_id']] = $row['respuesta_esperada'];
    }
    echo "Piccolo has " . count($piccoloAnswers) . " known answers in the database.\n\n";
    
    // 3. Create a new game
    echo "Creating new game...\n";
    $game = $service->createNewGame();
    $partidaId = $game['partidaId'];
    echo "Game ID: $partidaId\n\n";
    
    // 4. Play the game by answering questions as Piccolo would
    $step = 0;
    $maxSteps = 25;
    
    while ($step < $maxSteps) {
        $step++;
        
        // Get current question
        $questionId = $game['pregunta']['id'] ?? null;
        if (!$questionId) {
            echo "No question returned, game might have ended.\n";
            break;
        }
        
        echo "Step $step: Question ID = $questionId\n";
        echo "Question: {$game['pregunta']['texto']}\n";
        
        // Determine Piccolo's answer
        $answer = $piccoloAnswers[$questionId] ?? 'no lo se';
        echo "Piccolo's answer: $answer\n";
        
        // Submit answer
        $game = $service->responder((string)$partidaId, ['respuesta' => $answer]);
        
        // Check if we got a result
        if (isset($game['resultado'])) {
            echo "\n=== RESULT ===\n";
            echo "Character: {$game['resultado']['personaje']['nombre']}\n";
            echo "Confidence: {$game['resultado']['confianza']}%\n";
            echo "Is Correct: " . ($game['resultado']['personaje']['id'] == $piccoloId ? 'YES' : 'NO') . "\n";
            break;
        }
        
        echo "Progress: {$game['progreso']['paso']}/{$game['progreso']['total']}\n";
        echo "Confidence: {$game['confianza']}%\n\n";
    }
    
    if (!isset($game['resultado'])) {
        echo "\nGame did not end after $maxSteps steps.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
