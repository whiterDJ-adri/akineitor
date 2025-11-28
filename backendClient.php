<?php
// Cliente para el backend del Akineitor
class BackendClient
{
    private string $apiBaseUrl;

    public function __construct()
    {
        // Inicializar sesión si no está activa
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // URL base de la API (ajustar según tu configuración)
        // La API está en /api/index.php y maneja rutas que empiezan con /api

        // Detectar el host actual y construir la URL base dinámicamente
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Detectar si la URL actual contiene "/juego/" para decidir el prefijo
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $basePath = strpos($requestUri, '/projecte/') === 0 ? '/projecte' : '';

        $this->apiBaseUrl = $protocol . '://' . $host . $basePath . '/api/index.php';
    }

    private function makeApiCall(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        $url = $this->apiBaseUrl . $endpoint;

        $options = [
            'http' => [
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                'method' => $method,
                'timeout' => 30,
                'ignore_errors' => true // Para capturar códigos de error HTTP
            ]
        ];

        if ($method === 'POST' && !empty($data)) {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        error_log('[BackendClient] ' . $method . ' ' . $url);

        if ($response === false) {
            $error = error_get_last();
            throw new RuntimeException("Error al conectar con la API: $url. " . ($error['message'] ?? 'Error desconocido'));
        }

        // Verificar código de respuesta HTTP
        $httpCode = 200;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d (\d{3})/', $header, $matches)) {
                    $httpCode = (int) $matches[1];
                    break;
                }
            }
        }


        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error'] ?? $errorData['message'] ?? "Error HTTP $httpCode";
            throw new RuntimeException("Error de la API $this->apiBaseUrl: $errorMessage");
        }

        $decodedResponse = json_decode($response, true);
        error_log('[BackendClient] respuesta código ' . $httpCode . ' tamaño ' . strlen((string) $response));

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Error al decodificar respuesta JSON de la API: " . $response);
        }

        return $decodedResponse;
    }

    /**
     * Verifica si la API está disponible
     */
    public function verificarConectividad(): bool
    {
        try {
            $this->makeApiCall('/health', [], 'GET');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function crearPartida(): array
    {
        try {
            // Llamar a la API para crear una nueva partida (sin partida_id)
            $response = $this->makeApiCall('/api/algorithm/step', []);

            $partidaId = $response['partida_id'] ?? null;
            $preguntaActual = $response['pregunta_actual'] ?? null;
            $preguntasRespondidas = $response['preguntas_respondidas'] ?? 0;

            if (!$partidaId || !$preguntaActual) {
                throw new RuntimeException("Respuesta inválida de la API al crear partida");
            }

            // Convertir formato de API al formato esperado por el frontend
            $pregunta = [
                'id' => $preguntaActual['id'],
                'texto' => $preguntaActual['texto']
            ];

            $progreso = [
                'paso' => $preguntasRespondidas + 1,
                'total' => 12
            ];

            return [
                'partidaId' => $partidaId,
                'pregunta' => $pregunta,
                'progreso' => $progreso
            ];
        } catch (Exception $e) {
            throw new RuntimeException("Error al crear partida: " . $e->getMessage());
        }
    }

    public function responder(string $partidaId, array $payload): array
    {
        try {
            $respuesta = $payload['respuesta'] ?? null;
            $preguntaId = $payload['preguntaId'] ?? null;

            if ($respuesta === null || $respuesta === '' || $preguntaId === null) {
                throw new RuntimeException("Datos de respuesta incompletos");
            }

            // Llamar a la API con la partida y respuesta
            $apiData = [
                'partida_id' => $partidaId,
                'respuesta' => $respuesta
            ];

            $response = $this->makeApiCall('/api/algorithm/step', $apiData);

            $esFinal = $response['es_final'] ?? false;
            $preguntaActual = $response['pregunta_actual'] ?? null;
            $personajesPosibles = $response['personajes_posibles'] ?? [];
            $probabilidad = $response['probabilidad'] ?? 0;
            $preguntasRespondidas = $response['preguntas_respondidas'] ?? 0;

            if ($esFinal) {
                // Devolver resultado final
                $personajePrincipal = $personajesPosibles[0] ?? null;

                if (!$personajePrincipal) {
                    throw new RuntimeException("No se pudo determinar un personaje");
                }

                return [
                    'resultado' => [
                        'personaje' => [
                            'id' => $personajePrincipal['id'],
                            'nombre' => $personajePrincipal['nombre'],
                            'imagenUrl' => null // La API no devuelve imágenes por ahora
                        ],
                        'confianza' => $probabilidad,
                        'personajes_alternativos' => array_slice($personajesPosibles, 1, 4) // Hasta 4 alternativas
                    ]
                ];
            } else {
                // Devolver siguiente pregunta o degradar a resultado si falta la pregunta
                if (!$preguntaActual) {
                    $personajePrincipal = $personajesPosibles[0] ?? null;
                    if ($personajePrincipal) {
                        return [
                            'resultado' => [
                                'personaje' => [
                                    'id' => $personajePrincipal['id'],
                                    'nombre' => $personajePrincipal['nombre'],
                                    'imagenUrl' => null
                                ],
                                'confianza' => $probabilidad,
                                'personajes_alternativos' => array_slice($personajesPosibles, 1, 4)
                            ]
                        ];
                    }
                    return [
                        'resultado' => [
                            'personaje' => [
                                'id' => 0,
                                'nombre' => 'Desconocido',
                                'imagenUrl' => null
                            ],
                            'confianza' => 0,
                            'personajes_alternativos' => []
                        ]
                    ];
                }

                $pregunta = [
                    'id' => $preguntaActual['id'],
                    'texto' => $preguntaActual['texto']
                ];

                $progreso = [
                    'paso' => $preguntasRespondidas + 1,
                    'total' => 12
                ];

                return [
                    'pregunta' => $pregunta,
                    'progreso' => $progreso,
                    'personajes_posibles' => array_slice($personajesPosibles, 0, 5),
                    'confianza' => $probabilidad
                ];
            }
        } catch (Exception $e) {
            throw new RuntimeException("Error al procesar respuesta: " . $e->getMessage());
        }
    }

    public function reiniciar(string $partidaId): array
    {
        try {
            // Crear una nueva partida (ignoramos el partidaId anterior)
            return $this->crearPartida();
        } catch (Exception $e) {
            throw new RuntimeException("Error al reiniciar partida: " . $e->getMessage());
        }
    }

    public function corregir(string $partidaId, int $personajeId): array
    {
        try {
            $payload = [
                'partida_id' => $partidaId,
                'personaje_id' => $personajeId,
            ];
            $resp = $this->makeApiCall('/api/algorithm/correct', $payload);
            return $resp;
        } catch (Exception $e) {
            throw new RuntimeException("Error al corregir: " . $e->getMessage());
        }
    }

    public function sugerirPersonaje(string $nombre, ?string $descripcion = null, ?string $imagenUrl = null): array
    {
        try {
            $payload = [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'imagen_url' => $imagenUrl,
            ];
            $resp = $this->makeApiCall('/api/catalog/characters/create', $payload);
            return $resp;
        } catch (Exception $e) {
            throw new RuntimeException("Error al sugerir personaje: " . $e->getMessage());
        }
    }

    public function continuarPartida(string $partidaId): array
    {
        try {
            $payload = ['partida_id' => $partidaId];
            $response = $this->makeApiCall('/api/algorithm/continue', $payload);

            $esFinal = $response['es_final'] ?? false;
            $preguntaActual = $response['pregunta_actual'] ?? null;
            $personajesPosibles = $response['personajes_posibles'] ?? [];
            $probabilidad = $response['probabilidad'] ?? 0;
            $preguntasRespondidas = $response['preguntas_respondidas'] ?? 0;

            if ($esFinal) {
                // Devolver resultado final (aunque continue debería intentar dar otra pregunta)
                $personajePrincipal = $personajesPosibles[0] ?? null;

                if (!$personajePrincipal) {
                    throw new RuntimeException("No se pudo determinar un personaje");
                }

                return [
                    'resultado' => [
                        'personaje' => [
                            'id' => $personajePrincipal['id'],
                            'nombre' => $personajePrincipal['nombre'],
                            'imagenUrl' => null
                        ],
                        'confianza' => $probabilidad,
                        'personajes_alternativos' => array_slice($personajesPosibles, 1, 4)
                    ]
                ];
            } else {
                if (!$preguntaActual) {
                    throw new RuntimeException("La API no devolvió una pregunta válida al continuar.");
                }

                $pregunta = [
                    'id' => $preguntaActual['id'],
                    'texto' => $preguntaActual['texto']
                ];

                $progreso = [
                    'paso' => $preguntasRespondidas + 1,
                    'total' => 12
                ];

                return [
                    'pregunta' => $pregunta,
                    'progreso' => $progreso,
                    'personajes_posibles' => array_slice($personajesPosibles, 0, 5),
                    'confianza' => $probabilidad
                ];
            }
        } catch (Exception $e) {
            throw new RuntimeException("Error al continuar partida: " . $e->getMessage());
        }
    }
}
