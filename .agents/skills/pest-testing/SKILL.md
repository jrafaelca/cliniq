---
name: pest-testing
description: Guía de testing para este proyecto Laravel, priorizando estilo Pest y fallback a php artisan test cuando Pest no esté instalado.
---

# Pest Testing (Project Skill)

Usa este skill cuando la tarea implique crear, ajustar o ejecutar tests en este repositorio.

## Workflow recomendado

1. Identifica el tipo de test (`Feature` o `Unit`) y el comportamiento observable.
2. Prioriza cobertura de flujo completo (HTTP + validaciones + persistencia) para features.
3. Ejecuta tests focalizados primero y suite completa al final.

## Comandos

- Si existe Pest:
  - `vendor/bin/pest --filter <TestName>`
  - `vendor/bin/pest`
- Fallback estándar del proyecto:
  - `php artisan test --filter=<TestName>`
  - `php artisan test`

## Convenciones del proyecto

- Usar `RefreshDatabase` en tests de integración con BD.
- Validar respuestas Inertia con `assertInertia` cuando corresponda.
- Preferir assertions de comportamiento (`assertRedirect`, `assertJsonValidationErrors`, `assertDatabaseHas`) sobre detalles de implementación.

## Criterios de calidad

- Cubrir caminos feliz + errores de validación + permisos/autorización.
- Evitar tests frágiles dependientes de orden accidental o textos no contractuales.
- Mantener fixtures y factories mínimas, legibles y reutilizables.
