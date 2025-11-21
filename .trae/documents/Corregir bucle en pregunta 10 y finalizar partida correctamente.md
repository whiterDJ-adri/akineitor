## Diagnóstico rápido
- La partida se queda en un bucle alrededor de la pregunta 10 porque el contador de preguntas respondidas (`preguntas_respondidas`) no está aumentando de forma consistente.
- La causa más probable es que la API no está registrando la respuesta del usuario en `respuestas_partida` (por ejemplo, porque `ultima_pregunta_id` no está presente o la pregunta no existe, y entonces `recordAnswer` la descarta). Al no incrementarse `askedCount`, la selección de la siguiente pregunta vuelve a proponer preguntas sin acercarse a condición de finalización.
- Aunque ya añadimos un tope de 12 y umbrales más flexibles, si no se registran respuestas, el algoritmo no converge.

## Plan de corrección (backend API)
1. Registrar la respuesta con preferencia por `pregunta_id` del cliente:
   - En `AlgorithmController::step`, al recibir `respuesta`, usar `pregunta_id` del body si viene; si no, usar `estado['ultima_pregunta_id']`.
   - En `BackendClient::responder`, incluir `pregunta_id` a la API desde el `payload['preguntaId']`.
2. Robustecer `recordAnswer`:
   - Si `pregunta_id` no existe en BD, intentar registrar con `ultima_pregunta_id`.
   - Si aun así no se puede, añadir el `pregunta_id` al `estado_json` (`asked_ids`) para que el algoritmo lo considere como preguntado y evitar re-preguntar.
3. Unificar la fuente de “preguntas ya respondidas”:
   - Al calcular `askedIds`, combinar `respuestas_partida` con `estado_json['asked_ids']` para que el algoritmo no re-pregunte aunque una inserción fallara.
4. Finalización por baja ganancia de información:
   - Medir la entropía esperada de la siguiente pregunta; si la ganancia de información cae por debajo de un umbral tras ≥10 respuestas, finalizar con el mejor candidato actual.
   - Reducir el tope máximo a 12 (ya aplicado) y ajustar los umbrales de confianza para datasets incompletos.
5. Logging de diagnóstico:
   - Log de `askedCount`, `nextQId`, `topProb/secondProb` en `AlgorithmController::step` para poder depurar rápidamente si reaparece el bucle.

## Plan de corrección (cliente y UI)
1. Payload completo al responder:
   - `BackendClient::responder` pasará `pregunta_id` a la API.
2. Progreso coherente desde backend:
   - La API devolverá `preguntas_respondidas` y `es_final`; el cliente calculará `paso = preguntas_respondidas + (es_final ? 0 : 1)` y `total` dinámico desde configuración del servidor (12), para evitar desincronización.
3. Fallback en resultado:
   - Mantener el fallback actual: si la API no entrega `pregunta_actual`, el cliente construye resultado con el top candidato para no bloquear la UI.

## Validación
- Pruebas manuales: iniciar partida, contestar 12 preguntas variando respuestas y confirmar que se finaliza con resultado y alternativas.
- Prueba de integración: actualizar `test_api_integration.php` para validar que `preguntas_respondidas` aumenta y que el endpoint `step` finaliza dentro de 12 pasos.
- Revisión de logs: verificar en logs el incremento de `askedCount` y la evolución de `topProb`.

## Entregables
- Cambios en `AlgorithmController`, `AlgorithmService` y `BackendClient` para registro seguro de respuestas y prevención de bucles.
- Ajustes de umbrales y tope de preguntas.
- Logs de diagnóstico.

¿Confirmas que implementemos estos cambios ahora?