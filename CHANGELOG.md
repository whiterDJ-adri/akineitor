## Cambios recientes

### Eliminación del sistema de categorías
- Se eliminaron todas las referencias al sistema de categorías en `AlgorithmService`.
- Se removieron funciones: carga desde `data.json`, normalización de nombres, fallback de mapeo por categorías, priorización de preguntas por categorías, ponderación por relevancia de dominio y filtro de contradicciones basado en textos.
- `getAllPreguntas` ya no depende de la columna `category`.
- La selección de preguntas ahora se basa únicamente en ganancia de información (entropía) sin boosts específicos.

### Pruebas
- Se añadió `api/dist/test_no_categories.php` para verificar ausencia de métodos de categorías, y funcionamiento básico de API.
- Se actualizó `test_api_integration.php` para comprobar finalización dentro de 12 pasos.
- Se añadió `api/dist/test_name_hint.php` para validar la diferenciación por nombre entre Goku y Bulma (variaciones y acentos).

### Recomendaciones
- Mantener poblada la tabla `personaje_pregunta` para una mejor calidad de inferencias.
- Asegurar que existan preguntas suficientes en la tabla `preguntas`.
- Usar `nombre_hint` en `POST /api/algorithm/step` para sesgar la inferencia cuando el usuario introduzca un nombre explícito.