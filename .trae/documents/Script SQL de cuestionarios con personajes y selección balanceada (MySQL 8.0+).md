## Objetivo
Crear un script SQL integral para MySQL 8.0+ que:
- Defina el esquema (personajes, preguntas, opciones/respuestas, sesiones y progreso)
- Pueble el sistema con >20 preguntas (4 opciones, correcta marcada, dificultad: fácil/medio/difícil)
- Implemente procedimientos almacenados para selección aleatoria balanceada por dificultad y registro de progreso
- Incluya utilidades de población inicial (desde `data.json`), consultas de reporte y backup/restauración

## Esquema de Tablas
1. `personajes`
- `id` (PK), `nombre`, `descripcion`, `imagen_url`
- Población inicial desde `api/models/data.json` usando `JSON_TABLE`

2. `preguntas`
- `id` (PK), `texto_pregunta`, `tipo` (single_choice/multiple_choice), `dificultad` (ENUM: facil, medio, dificil), `tema` (opcional: raza, saga, transformaciones)

3. `opciones`
- `id` (PK), `pregunta_id` (FK), `texto_opcion`, `es_correcta` (BOOL)
- Garantiza exactamente 4 opciones por pregunta y 1 correcta

4. `sesiones`
- `id` (PK), `usuario_id` (NULLable), `estado` (in_progress/completed), `created_at`, `completed_at`, `total_preguntas`, `aciertos`

5. `sesion_preguntas`
- `sesion_id` (FK), `pregunta_id` (FK), `orden`, `respuesta_opcion_id` (NULLable), `es_correcta` (BOOL, NULLable)
- Única por (sesion_id, pregunta_id)

6. Índices y restricciones
- Índices en `preguntas(dificultad)` y `opciones(pregunta_id)`
- FK en cascada coherente (eliminar pregunta elimina opciones relacionadas)

## Procedimientos y Algoritmo
1. `sp_iniciar_sesion(IN p_usuario_id INT, IN p_total INT)`
- Crea sesión, selecciona lote de preguntas con balance de dificultad (p.ej., 40% fácil, 40% medio, 20% difícil)
- Selección aleatoria sin repetición; inserta en `sesion_preguntas` con `orden`

2. `sp_seleccionar_preguntas(IN p_total INT)` (interno)
- Calcula cupos por dificultad; usa `ORDER BY RAND()` con límites por categoría
- En caso de escasez de una dificultad, rellena con la siguiente disponible

3. `sp_responder(IN p_sesion_id INT, IN p_pregunta_id INT, IN p_opcion_id INT)`
- Registra la respuesta del usuario; valida opción correcta y marca `es_correcta`
- Actualiza `aciertos` en `sesiones`

4. `sp_progreso(IN p_sesion_id INT)`
- Devuelve preguntas respondidas/pendientes, porcentaje, aciertos y próximo `pregunta_id`

5. `sp_finalizar_si_corresponde(IN p_sesion_id INT)`
- Si todas las preguntas respondidas, marca sesión como `completed` y fecha

## Población de Datos
1. Población desde JSON (`api/models/data.json`)
- `sp_poblar_personajes_desde_json(IN p_path VARCHAR)`
- Usa `LOAD_FILE(p_path)` + `JSON_TABLE` para insertar `id`, `name`, `description`, `image` (normalizando nombres/acentos)

2. Población de preguntas/opciones
- `INSERT` para 24+ preguntas (temas: raza, saga, transformaciones, afiliación); 4 opciones por pregunta; marcar una correcta; dificultad variada

## Consultas de Reporte
- Top aciertos por sesión/usuario
- Distribución de dificultad seleccionada
- Análisis de preguntas más falladas

## Backup y Restauración
1. `sp_backup_full(IN p_suffix VARCHAR)`
- Crea tablas `*_bk_<suffix>` con `CREATE TABLE ... LIKE` + `INSERT INTO ... SELECT ...`

2. `sp_restore_full(IN p_suffix VARCHAR)`
- Limpia tablas actuales y repuebla desde backups

## Manejo de Errores y Transacciones
- `DECLARE EXIT HANDLER FOR SQLEXCEPTION` en SP críticos
- `START TRANSACTION` / `COMMIT` / `ROLLBACK` en iniciar sesión, responder y backup

## Ejemplos de Interacción
- Iniciar sesión (12 preguntas), consultar progreso, responder, finalizar
- Ejecutar backup y restauración

## Compatibilidad
- Respeta tablas existentes de tu proyecto; el script usa nuevas tablas (`opciones`, `sesiones`, `sesion_preguntas`) y puede añadir `dificultad` a `preguntas` si ya existe
- `personajes` se puebla desde `data.json`; si el servidor MySQL no tiene permiso `LOAD_FILE`, se incluye bloque alternativo con `INSERT` manual

¿Confirmas que procedamos a generar y entregar el script SQL completo con población de datos (>20 preguntas), procedimientos, reportes y backup/restauración conforme a este plan?