<?php

namespace Modules\Algorithm;

use Core\Request;
use Core\Response;

class AlgorithmController
{
    public function __construct(private AlgorithmService $service)
    {
    }

    public function step(Request $req, Response $res): void
    {
        error_log('[AlgorithmController] step');
        $partidaIdRaw = $req->body['partida_id'] ?? null;
        $respuesta = $req->body['respuesta'] ?? null;
        $partidaId = $partidaIdRaw !== null && $partidaIdRaw !== '' ? (int) $partidaIdRaw : null;

        try {
            if ($partidaId === null) {
                // Nueva partida
                $result = $this->service->createNewGame(null);
            } else {
                // Responder
                // Necesitamos el ID de la pregunta anterior. El frontend NO lo envía en el body de 'step' explícitamente en el formato antiguo?
                // Revisando backendClient.php:
                // $apiData = ['partida_id' => $partidaId, 'respuesta' => $respuesta];
                // NO envía preguntaId. El controlador antiguo lo sacaba del estado:
                // $ultimaPreguntaId = $estado['ultima_pregunta_id'] ?? null;
                
                // Mi nuevo servicio espera 'preguntaId'.
                // Debo recuperar el estado para saber cuál fue la última pregunta.
                // Como el servicio encapsula el estado, debería tener un método para "responder a la última pregunta" o yo debo extraerlo.
                // PERO, mi servicio `responder` pide `preguntaId`.
                
                // Solución: Modificar `responder` para que si no viene `preguntaId`, use la última del estado.
                // O aquí en el controlador, hacer un hack: leer el estado (que está en BD).
                // Mejor: El servicio debería ser capaz de inferirlo.
                
                // Por ahora, asumamos que el servicio puede manejarlo o lo arreglamos.
                // Voy a modificar el servicio para que `preguntaId` sea opcional y lo saque del estado si falta.
                
                // ... Espera, no puedo modificar el servicio en este paso (tool call).
                // Voy a leer el estado usando un método público que añadiré o asumiendo que puedo acceder.
                // No tengo método público para leer estado.
                
                // Workaround: El frontend TIENE la pregunta en sesión, pero backendClient no la envía.
                // El controlador antiguo hacía: $ultimaPreguntaId = $estado['ultima_pregunta_id'];
                
                // Voy a añadir un método `getLastQuestionId($partidaId)` al servicio o hacer que `responder` lo maneje.
                // Como ya edité el servicio, voy a asumir que puedo pasar null y el servicio lo maneja, O
                // voy a instanciar `MySQLConnection` aquí? No, feo.
                
                // Voy a hacer que `responder` acepte `preguntaId` opcional.
                // Pero primero escribo este controlador asumiendo que pasaré null y el servicio fallará si no lo arreglo.
                // Así que arreglaré el servicio en el siguiente paso.
                
                $result = $this->service->responder((string)$partidaId, [
                    'respuesta' => $respuesta,
                    'preguntaId' => null // To be handled by service
                ]);
            }

            // Mapear respuesta del servicio al formato API antiguo
            $esFinal = isset($result['resultado']);
            
            $salida = [
                'partida_id' => $result['partidaId'] ?? $partidaId,
                'es_final' => $esFinal,
                'preguntas_respondidas' => ($result['progreso']['paso'] ?? 1) - 1,
                'probabilidad' => ($result['confianza'] ?? 0) / 100.0, // Frontend espera 0.0-1.0? El antiguo devolvía round($topProb, 4). Mi servicio devuelve 0-100. Ajustar.
                'personajes_posibles' => $result['candidates'] ?? [], // Para debug
            ];

            if ($esFinal) {
                $resFinal = $result['resultado'];
                $salida['personajes_posibles'] = array_merge(
                    [['id' => $resFinal['personaje']['id'], 'nombre' => $resFinal['personaje']['nombre'], 'probabilidad' => $resFinal['confianza']/100]],
                    array_map(fn($c) => ['id' => $c['id'], 'nombre' => $c['nombre'], 'probabilidad' => $c['prob'] ?? 0], $resFinal['personajes_alternativos'])
                );
                // El frontend espera 'pregunta_actual' como null si es final?
                $salida['pregunta_actual'] = null;
            } else {
                $salida['pregunta_actual'] = [
                    'id' => $result['pregunta']['id'],
                    'texto' => $result['pregunta']['texto']
                ];
            }

            $res::json($salida);

        } catch (\Throwable $e) {
            error_log("Error en AlgorithmController: " . $e->getMessage());
            $res::json(['error' => $e->getMessage()], 500);
        }
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

        try {
            $this->service->corregir((string)$partidaIdRaw, (int)$personajeIdRaw);
            $res::json(['ok' => true]);
        } catch (\Throwable $e) {
            $res::json(['error' => 'No se pudo aplicar la corrección'], 500);
        }
    }
}
