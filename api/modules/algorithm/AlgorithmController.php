<?php

namespace Modules\Algorithm;

use Core\Request;
use Core\Response;

class AlgorithmController
{
    public function __construct(private AlgorithmService $service) {}

    public function step(Request $req, Response $res): void
    {
        error_log('[AlgorithmController] step');
        $partidaIdRaw = $req->body['partida_id'] ?? null;
        $nombreHint = $req->body['nombre_hint'] ?? null;
        $preguntaIdRaw = $req->body['pregunta_id'] ?? null;
        $respuesta = $req->body['respuesta'] ?? null;
        $partidaId = $partidaIdRaw !== null && $partidaIdRaw !== '' ? (int)$partidaIdRaw : null;

        if ($partidaId === null) {
            $created = $this->service->createNewGame(null);
            $partidaId = $created['partida_id'];
        }

        $partida = $this->service->getPartida($partidaId);
        $estadoJson = $partida['estado_json'] ?? null;
        $estado = [];
        if ($estadoJson) {
            $decoded = json_decode($estadoJson, true);
            if (is_array($decoded)) $estado = $decoded;
        }

        $WAIT_MS = 800;
        $nowMs = (int)round(microtime(true) * 1000);
        $ultimaPreguntaId = $estado['ultima_pregunta_id'] ?? null;
        $lastStepMs = (int)($estado['last_step_ms'] ?? 0);
        $waitRemaining = ($nowMs - $lastStepMs < $WAIT_MS) ? ($WAIT_MS - ($nowMs - $lastStepMs)) : 0;
        if ($respuesta && $ultimaPreguntaId && $waitRemaining > 0) {
            $preguntas = $this->service->getAllPreguntas();
            $preguntaActual = null;
            if ($ultimaPreguntaId && isset($preguntas[$ultimaPreguntaId])) {
                $pq = $preguntas[$ultimaPreguntaId];
                $preguntaActual = [
                    'id' => $pq['id'],
                    'texto' => $pq['texto_pregunta'],
                    'tipo' => $pq['tipo'],
                    'opciones' => $pq['opciones'] ?? [],
                ];
            }
            $salida = [
                'pregunta_actual' => $preguntaActual,
                'personajes_posibles' => [],
                'probabilidad' => 0.0,
                'preguntas_respondidas' => count($this->service->getAskedAnswers($partidaId)),
                'es_final' => false,
                'partida_id' => $partidaId,
                'espera_ms_restante' => $waitRemaining,
            ];
            $res::json($salida);
            return;
        }
        $pidToRecord = null;
        if ($preguntaIdRaw !== null && $preguntaIdRaw !== '') {
            $pidToRecord = (int)$preguntaIdRaw;
        } elseif ($ultimaPreguntaId) {
            $pidToRecord = (int)$ultimaPreguntaId;
        }
        if ($respuesta && $pidToRecord) {
            $this->service->recordAnswer($partidaId, $pidToRecord, (string)$respuesta);
            // Si la pregunta no existe en BD, persistir en estado para evitar bucles
            if (!$this->service->preguntaExists($pidToRecord)) {
                $estado['asked_state'] = $estado['asked_state'] ?? [];
                $estado['asked_state'][$pidToRecord] = (string)$respuesta;
                $this->service->updatePartidaEstadoJson($partidaId, $estado);
            }
        }

        $asked = $this->service->getAskedAnswers($partidaId);
        // Combinar con estado asked_state (en caso de inserción fallida en BD)
        $askedState = [];
        if (!empty($estado['asked_state']) && is_array($estado['asked_state'])) {
            $askedState = $estado['asked_state'];
        }
        foreach ($askedState as $qid => $ans) {
            if (!isset($asked[$qid])) $asked[(int)$qid] = (string)$ans;
        }
        $personajes = $this->service->getAllPersonajes();
        $preguntas = $this->service->getAllPreguntas();
        $mapping = $this->service->getMapping();

        if ($nombreHint !== null && $nombreHint !== '') {
            $estado['nombre_hint'] = (string)$nombreHint;
            $this->service->updatePartidaEstadoJson($partidaId, $estado);
        }
        // Filtrado inmediato de preguntas irrelevantes
        $estado['blocked_qids'] = $estado['blocked_qids'] ?? [];
        $estado['blocked_pids'] = $estado['blocked_pids'] ?? [];
        if ($respuesta && $pidToRecord && isset($preguntas[$pidToRecord])) {
            $txt = mb_strtolower($preguntas[$pidToRecord]['texto_pregunta'] ?? '');
            $ansNorm = mb_strtolower(trim($respuesta));
            if (strpos($txt, 'chicle') !== false && ($ansNorm === 'no' || $ansNorm === 'no lo sé' || $ansNorm === 'ns')) {
                foreach ($preguntas as $qid => $pq) {
                    $t = mb_strtolower($pq['texto_pregunta'] ?? '');
                    if (strpos($t, 'buu') !== false || strpos($t, 'boo') !== false || strpos($t, 'majin') !== false) {
                        $estado['blocked_qids'][$qid] = true;
                    }
                }
                foreach ($personajes as $pid => $pinfo) {
                    $n = mb_strtolower($pinfo['nombre'] ?? '');
                    if (strpos($n, 'buu') !== false || strpos($n, 'boo') !== false) {
                        $estado['blocked_pids'][$pid] = true;
                    }
                }
                $this->service->updatePartidaEstadoJson($partidaId, $estado);
            }
        }

        $probs = $this->service->computeProbabilities($asked, $personajes, $mapping);
        $probs = $this->service->biasByNameHint($probs, $personajes, $estado['nombre_hint'] ?? null);
        if (!empty($estado['blocked_pids'])) {
            $changed = false;
            foreach ($estado['blocked_pids'] as $bp => $flag) {
                if ($flag && isset($probs[$bp])) {
                    $probs[$bp] = 0.0; $changed = true;
                }
            }
            if ($changed) {
                $sum = array_sum($probs);
                if ($sum > 0) {
                    foreach ($probs as $pid => $p) { $probs[$pid] = $p / $sum; }
                }
            }
        }
        $askedIds = array_keys($asked);
        $filteredPreguntas = $preguntas;
        if (!empty($estado['blocked_qids'])) {
            foreach ($estado['blocked_qids'] as $bq => $flag) {
                if ($flag) unset($filteredPreguntas[$bq]);
            }
        }
        $nextQId = $this->service->selectNextQuestion($askedIds, $probs, $mapping, $filteredPreguntas);

        // Top y segundo para razón de confianza
        $sortedProbs = $probs;
        arsort($sortedProbs);
        $topPid = key($sortedProbs);
        $topProb = $sortedProbs[$topPid] ?? 0.0;
        $vals = array_values($sortedProbs);
        $secondProb = $vals[1] ?? 0.0;

        // Umbral dinámico según número de preguntas ya respondidas
        $askedCount = count($askedIds);
        $certThreshold = 0.4;
        $remainingCandidates = 0;
        foreach ($probs as $p) { if ($p > 0.05) $remainingCandidates++; }
        $esFinal = ($nextQId === null) || ($topProb >= $certThreshold) || (($askedCount >= 12) && ($remainingCandidates <= 3));
        error_log('[AlgorithmController] askedCount=' . $askedCount . ' topProb=' . $topProb . ' secondProb=' . $secondProb . ' nextQId=' . ($nextQId ?? -1) . ' esFinal=' . ($esFinal ? '1' : '0'));

        if ($esFinal) {
            $this->service->completePartida($partidaId);
        } else {
            $estado['ultima_pregunta_id'] = $nextQId;
            $estado['last_step_ms'] = $nowMs;
            $this->service->updatePartidaEstadoJson($partidaId, $estado);
        }

        // Construir salida de personajes con probabilidad
        $personajesPosibles = [];
        foreach ($probs as $pid => $p) {
            $personajesPosibles[] = [
                'id' => $pid,
                'nombre' => $personajes[$pid]['nombre'] ?? (string)$pid,
                'probabilidad' => round($p, 4),
            ];
        }
        // ordenar desc
        usort($personajesPosibles, fn($a, $b) => $b['probabilidad'] <=> $a['probabilidad']);

        $preguntaActual = null;
        if (!$esFinal && $nextQId !== null && isset($preguntas[$nextQId])) {
            $pq = $preguntas[$nextQId];
            $preguntaActual = [
                'id' => $pq['id'],
                'texto' => $pq['texto_pregunta'],
                'tipo' => $pq['tipo'],
            ];
        }

        $mensaje = null;
        if ($esFinal && isset($personajes[$topPid]['nombre'])) {
            $n = $personajes[$topPid]['nombre'];
            if (!empty($estado['nombre_hint'])) {
                $hn = $estado['nombre_hint'];
                $mensaje = (mb_strtolower($hn) === mb_strtolower($n)) ? ('Entrada reconocida: ' . $n) : ('Resultado: ' . $n);
            } else {
                $mensaje = 'Resultado: ' . $n;
            }
        }
        $confirmacionRequerida = ($askedCount >= 12) && ($remainingCandidates <= 3) && ($topProb < $certThreshold);
        $totalPreguntas = count($preguntas);
        $salida = [
            'pregunta_actual' => $preguntaActual,
            'personajes_posibles' => $personajesPosibles,
            'probabilidad' => round($topProb, 4),
            'preguntas_respondidas' => $askedCount,
            'es_final' => $esFinal,
            'partida_id' => $partidaId,
            'hint_info' => isset($estado['nombre_hint']) ? ['nombre_hint' => $estado['nombre_hint']] : null,
            'mensaje' => $mensaje,
            'total_preguntas' => $totalPreguntas,
            'confirmacion_requerida' => $confirmacionRequerida,
        ];
        $res::json($salida);
    }

    public function correct(Request $req, Response $res): void
    {
        error_log('[AlgorithmController] correct');
        $partidaIdRaw = $req->body['partida_id'] ?? null;
        $personajeIdRaw = $req->body['personaje_id'] ?? null;
        if ($partidaIdRaw === null || $personajeIdRaw === null) {
            $res::json(['error' => 'Datos incompletos'], 400);
            return;
        }
        $partidaId = (int)$partidaIdRaw;
        $personajeId = (int)$personajeIdRaw;
        try {
            $this->service->applyCorrection($partidaId, $personajeId);
            $res::json(['ok' => true]);
        } catch (\Throwable $e) {
            $res::json(['error' => 'No se pudo aplicar la corrección'], 500);
        }
    }

    public function continue(Request $req, Response $res): void
    {
        error_log('[AlgorithmController] continue');
        $partidaIdRaw = $req->body['partida_id'] ?? null;
        if ($partidaIdRaw === null) {
            $res::json(['error' => 'partida_id requerido'], 400);
            return;
        }
        $partidaId = (int)$partidaIdRaw;
        $partida = $this->service->getPartida($partidaId);
        $estadoJson = $partida['estado_json'] ?? null;
        $estado = [];
        if ($estadoJson) {
            $decoded = json_decode($estadoJson, true);
            if (is_array($decoded)) $estado = $decoded;
        }

        $asked = $this->service->getAskedAnswers($partidaId);
        $personajes = $this->service->getAllPersonajes();
        $preguntas = $this->service->getAllPreguntas();
        $mapping = $this->service->getMapping();
        $probs = $this->service->computeProbabilities($asked, $personajes, $mapping);
        $askedIds = array_keys($asked);
        $nextQId = $this->service->selectNextQuestion($askedIds, $probs, $mapping, $preguntas);

        if ($nextQId === null) {
            $sortedProbs = $probs; arsort($sortedProbs);
            $topPid = key($sortedProbs);
            $topProb = $sortedProbs[$topPid] ?? 0.0;
            $res::json([
                'pregunta_actual' => null,
                'personajes_posibles' => [],
                'probabilidad' => round($topProb, 4),
                'preguntas_respondidas' => count($askedIds),
                'es_final' => true,
                'partida_id' => $partidaId,
                'total_preguntas' => count($preguntas),
            ]);
            return;
        }

        $estado['ultima_pregunta_id'] = $nextQId;
        $this->service->updatePartidaEstadoJson($partidaId, $estado);
        $pq = $preguntas[$nextQId];
        $res::json([
            'pregunta_actual' => [
                'id' => $pq['id'],
                'texto' => $pq['texto_pregunta'],
                'tipo' => $pq['tipo'],
                'opciones' => $pq['opciones'] ?? [],
            ],
            'personajes_posibles' => [],
            'probabilidad' => 0.0,
            'preguntas_respondidas' => count($askedIds),
            'es_final' => false,
            'partida_id' => $partidaId,
            'total_preguntas' => count($preguntas),
        ]);
    }
}
