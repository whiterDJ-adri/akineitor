<?php

namespace Modules\Algorithm;

use Core\Request;
use Core\Response;

class AlgorithmController
{
    public function __construct(private AlgorithmService $service) {}

    public function step(Request $req, Response $res): void
    {
        $partidaIdRaw = $req->body['partida_id'] ?? null;
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

        $ultimaPreguntaId = $estado['ultima_pregunta_id'] ?? null;
        if ($respuesta && $ultimaPreguntaId) {
            $this->service->recordAnswer($partidaId, (int)$ultimaPreguntaId, (string)$respuesta);
        }

        $asked = $this->service->getAskedAnswers($partidaId);
        $personajes = $this->service->getAllPersonajes();
        $preguntas = $this->service->getAllPreguntas();
        $mapping = $this->service->getMapping();

        $probs = $this->service->computeProbabilities($asked, $personajes, $mapping);
        $askedIds = array_keys($asked);
        $nextQId = $this->service->selectNextQuestion($askedIds, $probs, $mapping, $preguntas);

        // Top y segundo para razón de confianza
        $sortedProbs = $probs;
        arsort($sortedProbs);
        $topPid = key($sortedProbs);
        $topProb = $sortedProbs[$topPid] ?? 0.0;
        $vals = array_values($sortedProbs);
        $secondProb = $vals[1] ?? 0.0;

        // Umbral dinámico según número de preguntas ya respondidas
        $askedCount = count($askedIds);
        $threshold = 0.85;
        if ($askedCount >= 8) $threshold = 0.8;
        if ($askedCount >= 10) $threshold = 0.75;
        if ($askedCount >= 12) $threshold = 0.7;
        $ratioThreshold = 1.6;
        $hasStrongLead = ($secondProb > 0) && (($topProb / $secondProb) >= $ratioThreshold);
        $esFinal = ($nextQId === null) || ($topProb >= $threshold) || $hasStrongLead;

        if ($esFinal) {
            $this->service->completePartida($partidaId);
        } else {
            $estado['ultima_pregunta_id'] = $nextQId;
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
                'opciones' => $pq['opciones'],
            ];
        }

        $salida = [
            'pregunta_actual' => $preguntaActual,
            'personajes_posibles' => $personajesPosibles,
            'probabilidad' => round($topProb, 4),
            'preguntas_respondidas' => $askedCount,
            'es_final' => $esFinal,
            'partida_id' => $partidaId,
        ];
        $res::json($salida);
    }
}
