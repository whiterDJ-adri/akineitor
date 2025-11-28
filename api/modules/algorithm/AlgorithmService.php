<?php

namespace Modules\Algorithm;

use Exception;
use MySQLConnection;

require_once __DIR__ . '/AttributeMapper.php';

class AlgorithmService
{
    private MySQLConnection $db;
    private array $characters = [];
    private array $questions = [];
    // Cache de respuestas conocidas: [char_id][question_id] => 'sí'|'no'|...
    private array $knowledgeBase = [];

    public function __construct()
    {
        $this->db = new MySQLConnection();
        $this->loadData();
    }

    /**
     * Carga personajes y preguntas de la BD.
     */
    private function loadData(): void
    {
        // 1. Cargar Personajes (BD + JSON merge)
        // Primero cargamos del JSON para tener los atributos base
        // 1. Cargar Personajes de la DB (Fuente única de verdad)
        $res = $this->db->query("SELECT * FROM personajes");
        while ($row = $res->fetch_assoc()) {
            $id = (int) $row['id'];
            // Mapear columnas de BD a claves esperadas por AttributeMapper
            $this->characters[$id] = [
                'id' => $id,
                'name' => $row['nombre'],
                'description' => $row['descripcion'],
                'image' => $row['imagen_url'],
                'race' => $row['race'],
                'gender' => $row['gender'],
                'affiliation' => $row['affiliation'],
                'ki' => $row['ki'],
                'maxKi' => $row['maxKi']
            ];
        }

        // 2. Cargar Preguntas
        $res = $this->db->query("SELECT * FROM preguntas");
        while ($row = $res->fetch_assoc()) {
            $this->questions[(int) $row['id']] = $row;
        }

        // 3. Cargar Respuestas Aprendidas (Knowledge Base)
        $res = $this->db->query("SELECT * FROM personaje_pregunta");
        while ($row = $res->fetch_assoc()) {
            $pid = (int) $row['personaje_id'];
            $qid = (int) $row['pregunta_id'];
            $ans = $row['respuesta_esperada'];
            $this->knowledgeBase[$pid][$qid] = $ans;
        }
    }

    public function createNewGame(?int $usuarioId = null): array
    {
        // Crear registro en BD
        $sql = "INSERT INTO partidas (usuario_id, estado, fecha_inicio) VALUES (?, 'in_progress', NOW())";
        // Nota: Ajustar campos según esquema real si difiere. Asumo esquema estándar.
        // Si falla por columnas, ajustaremos. Basado en lectura anterior:
        // campos: usuario_id, personaje_objetivo_id, estado, estado_json

        $userIdVal = $usuarioId ? (string) $usuarioId : null;
        // No definimos objetivo a priori en modo adivinanza real, pero el esquema anterior lo hacía.
        // Lo dejaremos NULL o un random si es requerido.

        $this->db->query("INSERT INTO partidas (usuario_id, estado, estado_json) VALUES (?, 'in_progress', NULL)", [$userIdVal]);

        $res = $this->db->query("SELECT LAST_INSERT_ID() as id");
        $partidaId = (int) $res->fetch_assoc()['id'];

        // Estado inicial: Probabilidad uniforme
        $initialState = [
            'asked_questions' => [], // [id => respuesta]
            'candidates' => array_keys($this->characters), // IDs
            'probabilities' => [], // [id => float]
            'max_steps' => 20
        ];

        // Inicializar probabilidades uniformes
        $count = count($this->characters);
        if ($count > 0) {
            $prob = 1.0 / $count;
            foreach ($this->characters as $id => $char) {
                $initialState['probabilities'][$id] = $prob;
            }
        }

        // Seleccionar primera pregunta
        $nextQuestionId = $this->selectBestQuestion($initialState);
        $initialState['current_question_id'] = $nextQuestionId;

        $this->saveGameState($partidaId, $initialState);

        // Obtener top candidatos para la respuesta
        $candidates = $this->getTopCandidates($initialState, 5);

        return [
            'partidaId' => $partidaId,
            'pregunta' => $this->formatQuestion($nextQuestionId),
            'progreso' => ['paso' => 1, 'total' => 20],
            'candidates' => $candidates,
            'confianza' => 0.0
        ];
    }

    public function responder(string $partidaId, array $payload): array
    {
        $partidaIdInt = (int) $partidaId;
        $respuesta = $payload['respuesta'];
        $preguntaId = $payload['preguntaId'] ?? null;

        // 1. Cargar estado
        $state = $this->getGameState($partidaIdInt);
        if (!$state)
            throw new Exception("Partida no encontrada");

        // Si no viene preguntaId, usar la última preguntada (la que estamos respondiendo)
        // El estado tiene 'asked_questions', pero ahí ya están las respondidas? No, 'asked_questions' guarda las respuestas.
        // Necesitamos saber cuál fue la ÚLTIMA pregunta generada.
        // Mi implementación anterior de `createNewGame` y `responder` NO guardaba la "pregunta actual pendiente" en el JSON, solo devolvía el ID.
        // Debo guardar `current_question_id` en el estado.

        if ($preguntaId === null) {
            $preguntaId = $state['current_question_id'] ?? null;
            if ($preguntaId === null)
                throw new Exception("No se pudo determinar la pregunta a responder");
        }

        // 2. Actualizar estado (Bayes)
        $state['asked_questions'][$preguntaId] = $respuesta;
        $state = $this->updateProbabilities($state, $preguntaId, $respuesta);

        // 3. Guardar respuesta en histórico
        $this->db->query(
            "INSERT INTO respuestas_partida (partida_id, pregunta_id, respuesta_usuario) VALUES (?, ?, ?)",
            [(string) $partidaId, (string) $preguntaId, $respuesta]
        );

        // 4. Verificar condición de victoria o parada
        $topCandidateId = $this->getTopCandidate($state['probabilities']);
        $topProb = $state['probabilities'][$topCandidateId] ?? 0;
        $steps = count($state['asked_questions']);

        // Obtener candidatos ordenados para la respuesta
        $candidates = $this->getTopCandidates($state, 5);

        // Umbral de certeza o límite de preguntas
        $maxSteps = $state['max_steps'] ?? 20;
        
        // Decrementar pasos forzados si existen
        $forceContinue = false;
        if (isset($state['forced_steps']) && $state['forced_steps'] > 0) {
            $state['forced_steps']--;
            $forceContinue = true;
        }

        if (!$forceContinue && ($topProb > 0.85 || $steps >= $maxSteps || count($state['candidates']) <= 1)) {
            // FIN DEL JUEGO
            error_log("RESULT: topProb=$topProb, steps=$steps, candidates=" . count($state['candidates']));
            error_log("Probabilities: " . json_encode($state['probabilities']));

            // Ensure minimum confidence display
            $displayProb = max($topProb, 0.01); // At least 1% for display

            $this->db->query("UPDATE partidas SET estado = 'completed' WHERE id = ?", [(string) $partidaId]);
            $personaje = $this->characters[$topCandidateId];

            // Alternativas son los candidatos menos el primero
            $alternatives = array_slice($candidates, 1);

            return [
                'resultado' => [
                    'personaje' => [
                        'id' => $personaje['id'],
                        'nombre' => $personaje['name'] ?? $personaje['nombre'],
                        'imagenUrl' => $personaje['image'] ?? $personaje['imagen_url'] ?? null,
                        'descripcion' => $personaje['description'] ?? $personaje['descripcion'] ?? ''
                    ],
                    'confianza' => round(max($displayProb, 0.01) * 100, 1),
                    'personajes_alternativos' => $alternatives
                ]
            ];
        }

        // 5. Seleccionar siguiente pregunta
        $nextQuestionId = $this->selectBestQuestion($state);

        if (!$nextQuestionId) {
            return [
                'resultado' => [
                    'personaje' => [
                        'id' => $topCandidateId,
                        'nombre' => $this->characters[$topCandidateId]['name'] ?? 'Desconocido',
                        'imagenUrl' => null
                    ],
                    'confianza' => round(max($topProb, 0.01) * 100, 1),
                    'personajes_alternativos' => []
                ]
            ];
        }

        $state['current_question_id'] = $nextQuestionId;
        $this->saveGameState($partidaIdInt, $state);

        return [
            'pregunta' => $this->formatQuestion($nextQuestionId),
            'progreso' => ['paso' => $steps + 1, 'total' => 20],
            'confianza' => round(max($topProb, 0.01) * 100, 1),
            'candidates' => $candidates
        ];
    }

    public function continueGame(string $partidaId): array
    {
        $partidaIdInt = (int) $partidaId;
        $state = $this->getGameState($partidaIdInt);
        if (!$state)
            throw new Exception("Partida no encontrada");

        // 1. Identificar al candidato que se mostró (el top 1 actual)
        $topCandidateId = $this->getTopCandidate($state['probabilities']);

        // 2. Eliminarlo de las probabilidades (poner a 0)
        unset($state['probabilities'][$topCandidateId]);

        // También podríamos añadirlo a una lista de "rejected_candidates" en el estado para no volver a considerarlo
        // si se recalcula desde cero, pero aquí estamos modificando las probabilidades actuales.
        $state['rejected_candidates'][] = $topCandidateId;

        // 3. Renormalizar probabilidades restantes
        $totalProb = array_sum($state['probabilities']);
        if ($totalProb > 0) {
            foreach ($state['probabilities'] as $id => $p) {
                $state['probabilities'][$id] = $p / $totalProb;
            }
        } else {
            // Si no queda nadie (raro), resetear a uniforme con los que quedan
            // O si literalmente no queda nadie en candidates, estamos en problemas.
            // Asumamos que quedan candidatos.
            $remaining = array_keys($state['probabilities']);
            if (empty($remaining)) {
                // Fallback drástico: revivir a todos menos los rechazados
                // Por ahora, lanzamos error o devolvemos desconocido
                throw new Exception("No quedan más personajes posibles.");
            }
            $prob = 1.0 / count($remaining);
            foreach ($remaining as $id) {
                $state['probabilities'][$id] = $prob;
            }
        }

        // 4. Seleccionar nueva pregunta
        $nextQuestionId = $this->selectBestQuestion($state);

        if (!$nextQuestionId) {
            // Si no hay más preguntas útiles, devolver el siguiente mejor candidato directamente?
            // O simplemente fallar. Intentemos devolver el siguiente.
            $newTop = $this->getTopCandidate($state['probabilities']);
            return [
                'resultado' => [
                    'personaje' => [
                        'id' => $newTop,
                        'nombre' => $this->characters[$newTop]['name'] ?? 'Desconocido',
                        'imagenUrl' => null
                    ],
                    'confianza' => round(max($state['probabilities'][$newTop] ?? 0, 0.01) * 100, 1),
                    'personajes_alternativos' => []
                ]
            ];
        }

        $state['current_question_id'] = $nextQuestionId;
        
        // Configurar pasos forzados para asegurar 5-6 preguntas más
        // Aumentamos el límite total y forzamos 5 preguntas sin interrupción
        $currentSteps = count($state['asked_questions']);
        $state['max_steps'] = $currentSteps + 6;
        $state['forced_steps'] = 5;

        // 5. Actualizar estado en BD y poner estado 'in_progress' por si estaba 'completed'
        $this->saveGameState($partidaIdInt, $state);
        $this->db->query("UPDATE partidas SET estado = 'in_progress' WHERE id = ?", [(string) $partidaId]);

        $topProb = $state['probabilities'][$this->getTopCandidate($state['probabilities'])] ?? 0;
        $steps = count($state['asked_questions']);

        return [
            'pregunta' => $this->formatQuestion($nextQuestionId),
            'progreso' => ['paso' => $steps + 1, 'total' => 20],
            'confianza' => round(max($topProb, 0.01) * 100, 1),
            'candidates' => $this->getTopCandidates($state, 5)
        ];
    }

    public function corregir(string $partidaId, int $personajeCorrectoId): void
    {
        // Aprender de los errores
        $state = $this->getGameState((int) $partidaId);
        if (!$state)
            return;

        foreach ($state['asked_questions'] as $qid => $userAnswer) {
            // Solo aprendemos si la respuesta fue definitiva (sí/no)
            if (in_array($userAnswer, ['sí', 'no'])) {
                // Verificar si ya existe
                $exists = $this->db->query(
                    "SELECT 1 FROM personaje_pregunta WHERE personaje_id = ? AND pregunta_id = ?",
                    [(string) $personajeCorrectoId, (string) $qid]
                );

                if ($exists->num_rows > 0) {
                    $this->db->query(
                        "UPDATE personaje_pregunta SET respuesta_esperada = ? WHERE personaje_id = ? AND pregunta_id = ?",
                        [$userAnswer, (string) $personajeCorrectoId, (string) $qid]
                    );
                } else {
                    $this->db->query(
                        "INSERT INTO personaje_pregunta (personaje_id, pregunta_id, respuesta_esperada) VALUES (?, ?, ?)",
                        [(string) $personajeCorrectoId, (string) $qid, $userAnswer]
                    );
                }
            }
        }
    }

    // --- Lógica Bayesiana ---

    private function updateProbabilities(array $state, int $questionId, string $answer): array
    {
        $probs = $state['probabilities'];
        $newProbs = [];
        $totalProb = 0.0;

        foreach ($probs as $charId => $prior) {
            // P(E|H) - Probabilidad de la evidencia (respuesta) dada la hipótesis (personaje)
            $likelihood = $this->calculateLikelihood($charId, $questionId, $answer);

            $posterior = $prior * $likelihood;
            $newProbs[$charId] = $posterior;
            $totalProb += $posterior;
        }

        // Normalizar
        if ($totalProb > 0) {
            foreach ($newProbs as $id => $p) {
                $newProbs[$id] = $p / $totalProb;
            }
        } else {
            // Si todo es 0 (contradicción total), resetear a uniforme o mantener anterior (soft fail)
            // Aquí mantenemos anterior pero atenuado
            return $state;
        }

        // Filtrar candidatos con probabilidad muy baja para optimizar
        $finalProbs = [];
        foreach ($newProbs as $id => $p) {
            if ($p > 0.0001) { // 0.01% threshold - keep more candidates longer to avoid premature elimination
                $finalProbs[$id] = $p;
            }
        }

        // CRITICAL: Si el filtrado eliminó TODOS los candidatos, mantener al menos el mejor
        if (empty($finalProbs) && !empty($newProbs)) {
            error_log("WARNING: All candidates filtered out, keeping top candidate");
            // Encontrar el candidato con mayor probabilidad aunque sea < 0.001
            arsort($newProbs);
            $topId = array_key_first($newProbs);
            $finalProbs[$topId] = 1.0; // Le damos 100% ya que es el único
        }

        // Renormalizar tras corte
        $totalFinal = array_sum($finalProbs);
        if ($totalFinal > 0) {
            foreach ($finalProbs as $id => $p) {
                $finalProbs[$id] = $p / $totalFinal;
            }
        }

        $state['probabilities'] = $finalProbs;
        $state['candidates'] = array_keys($finalProbs);

        return $state;
    }

    private function calculateLikelihood(int $charId, int $questionId, string $userAnswer): float
    {
        // Obtener la respuesta "real" del personaje
        $expected = $this->getCharacterAnswer($charId, $questionId);

        // Normalize answers to handle accent variations
        $expected = $this->normalizeAnswer($expected);
        $userAnswer = $this->normalizeAnswer($userAnswer);

        // Matriz de confusión simple
        // Fila: Respuesta Esperada, Columna: Respuesta Usuario
        // Valores: Probabilidad P(UserAnswer | TrueAnswer)

        // Simplificación:
        if ($expected === 'no lo se')
            return 1.0; // Si el personaje no sabe, cualquier respuesta es neutra (o 0.5)

        $matchProb = 0.9; // Usuario acierta
        $mismatchProb = 0.1; // Usuario se equivoca

        // Mapeo de respuestas a valores numéricos aproximados para distancia
        // sí=1, probablemente=0.75, no lo sé=0.5, probablemente no=0.25, no=0

        if ($userAnswer === 'no lo se')
            return 0.5; // Usuario no sabe, impacto bajo

        $isMatch = ($expected === $userAnswer);

        // Manejo de "probablemente"
        if (str_contains($userAnswer, 'probablemente')) {
            if (str_contains($userAnswer, 'si') && $expected === 'si')
                return 0.7;
            if (str_contains($userAnswer, 'no') && $expected === 'no')
                return 0.7;
            if ($expected === 'no lo se')
                return 0.5;
            return 0.3; // Discrepancia suave
        }

        return $isMatch ? $matchProb : $mismatchProb;
    }

    /**
     * Normalize answer to handle accent variations
     * si/sí -> si
     * no se/no sé/no lo sé -> no lo se
     */
    private function normalizeAnswer(string $answer): string
    {
        $answer = mb_strtolower(trim($answer));

        // Normalize accented versions
        $answer = str_replace(['sí', 'í'], ['si', 'i'], $answer);
        $answer = str_replace(['é'], ['e'], $answer);

        // Normalize "no sé" variations
        if (in_array($answer, ['no se', 'no lo se', 'no se', 'no lo sé', 'no sé'])) {
            return 'no lo se';
        }

        // Normalize probablemente variations
        if (str_contains($answer, 'probablemente')) {
            if (str_contains($answer, 'si'))
                return 'probablemente si';
            if (str_contains($answer, 'no'))
                return 'probablemente no';
        }

        return $answer;
    }

    private function getCharacterAnswer(int $charId, int $questionId): string
    {
        // 1. Buscar en BD (Conocimiento aprendido)
        if (isset($this->knowledgeBase[$charId][$questionId])) {
            return $this->knowledgeBase[$charId][$questionId];
        }

        // 2. Inferir de atributos (AttributeMapper)
        $charData = $this->characters[$charId] ?? [];
        $questionText = $this->questions[$questionId]['texto_pregunta'] ?? '';

        return AttributeMapper::getAnswer($charData, $questionText);
    }

    private function selectBestQuestion(array $state): ?int
    {
        $candidates = $state['candidates'];
        $probs = $state['probabilities'];
        $asked = $state['asked_questions'];

        $bestQ = null;
        $maxScore = -1.0;

        foreach ($this->questions as $qid => $qData) {
            if (isset($asked[$qid]))
                continue;

            $pYes = 0.0;
            $pNo = 0.0;
            $pUnknown = 0.0;

            foreach ($candidates as $cid) {
                $charProb = $probs[$cid];
                $ans = $this->getCharacterAnswer($cid, $qid);

                if ($ans === 'sí') {
                    $pYes += $charProb;
                } elseif ($ans === 'no') {
                    $pNo += $charProb;
                } else {
                    $pUnknown += $charProb;
                }
            }

            // Si la mayoría es "no lo sé", la pregunta es inútil
            if ($pUnknown > 0.5)
                continue;

            // Normalizar Yes/No ignorando Unknown para calcular poder discriminativo
            $knownMass = $pYes + $pNo;
            if ($knownMass < 0.01)
                continue; // Nadie sabe nada

            $normYes = $pYes / $knownMass;
            $normNo = $pNo / $knownMass;

            // Entropía sobre la masa conocida
            $entropy = 0.0;
            if ($normYes > 0)
                $entropy -= $normYes * log($normYes, 2);
            if ($normNo > 0)
                $entropy -= $normNo * log($normNo, 2);

            // Penalizar por masa desconocida
            // Score = Entropy * KnownMass
            // Si KnownMass es 1.0 (todos conocidos), Score = Entropy
            // Si KnownMass es 0.1 (casi nadie conocido), Score es bajo
            $score = $entropy * $knownMass;

            if ($score > $maxScore) {
                $maxScore = $score;
                $bestQ = $qid;
            }
        }

        return $bestQ;
    }

    // --- Helpers ---

    private function saveGameState(int $partidaId, array $state): void
    {
        $json = json_encode($state);
        $this->db->query("UPDATE partidas SET estado_json = ? WHERE id = ?", [$json, (string) $partidaId]);
    }

    private function getGameState(int $partidaId): ?array
    {
        $res = $this->db->query("SELECT estado_json FROM partidas WHERE id = ?", [(string) $partidaId]);
        $row = $res->fetch_assoc();
        if (!$row || !$row['estado_json'])
            return null;
        return json_decode($row['estado_json'], true);
    }

    private function getTopCandidate(array $probs): int
    {
        $maxP = -1.0;
        $bestId = 0;
        foreach ($probs as $id => $p) {
            if ($p > $maxP) {
                $maxP = $p;
                $bestId = $id;
            }
        }

        // Si no encontramos nada mejor que -1, devolver el primer candidato
        if ($maxP < 0) {
            $bestId = array_key_first($probs);
        }

        return $bestId;
    }

    private function getTopCandidates(array $state, int $limit): array
    {
        $probs = $state['probabilities'];
        arsort($probs);
        $candidates = [];
        $count = 0;
        foreach ($probs as $pid => $prob) {
            if ($count++ >= $limit)
                break;
            $candidates[] = [
                'id' => $pid,
                'nombre' => $this->characters[$pid]['name'] ?? $this->characters[$pid]['nombre'],
                'probabilidad' => $prob > 0 ? max($prob, 0.0001) : 0
            ];
        }
        return $candidates;
    }

    private function formatQuestion(int $qid): ?array
    {
        if (!isset($this->questions[$qid]))
            return null;
        return [
            'id' => $qid,
            'texto' => $this->questions[$qid]['texto_pregunta']
        ];
    }

    // Métodos stub para compatibilidad si son llamados desde fuera (aunque no deberían)
    public function getPartida(int $id)
    {
        return [];
    }
}
