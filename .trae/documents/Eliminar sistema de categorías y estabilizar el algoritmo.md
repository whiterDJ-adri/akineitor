## Alcance de limpieza
- Eliminar por completo el sistema de categorías en el motor (`AlgorithmService`):
  - Propiedades y carga desde `data.json` (`jsonCharsByName`, `loadJsonCharacters`, `normalizeName`).
  - Fallback de mapeo por categorías en `getMapping`.
  - Priorización de preguntas por categorías en `selectNextQuestion`.
  - Ponderación por relevancia de dominio (`domainRelevanceBoost`).
  - Filtro de contradicciones/redundancias basado en textos de categorías.
- Asegurar que `getAllPreguntas` no dependa de columnas de categorías (p.ej., `category`).

## Ajustes para funcionamiento estable
- `getMapping`: usar exclusivamente `personaje_pregunta` de BD; si falta, devolver 'no lo sé'.
- `selectNextQuestion`: seleccionar por máxima ganancia de información sin boosts ni filtros de categoría; mantener top-K.
- Mantener las demás funcionalidades (persistencia, cálculo de probabilidades, corrección, UI) intactas.

## Pruebas exhaustivas
- Crear `api/dist/test_no_categories.php`:
  - Verificar que métodos de categorías no existen (`method_exists` false).
  - Simular una partida: crear sesión vía API y avanzar 5 pasos, comprobar que no hay errores y que `es_final` se alcanza en ≤ 12.
  - Comprobar que `getAllPreguntas` funciona y no accede a `category`.
- Actualizar `test_api_integration.php` para validar flujo con respuestas y que aparece resultado.

## Documentación
- Añadir `CHANGELOG.md` con resumen de cambios: eliminación del sistema de categorías, impacto y recomendaciones (poblar `personaje_pregunta` en BD para calidad de inferencia).

## Entregables
- Código limpiado sin referencias a categorías
- Scripts de prueba
- Changelog

¿Procedo a implementar estos cambios y pruebas ahora?