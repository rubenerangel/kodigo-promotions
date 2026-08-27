# Flujo de trabajo del proyecto

## Roles

- **Orquestador/arquitecto:** analiza el requisito, consulta subagentes pertinentes, crea el plan, controla el alcance, coordina iteraciones y presenta el resultado a Rubén.
- **Rubén:** pilota la IA, aprueba el plan, revisa la implementación y decide la aceptación final.
- **Coder:** implementa exclusivamente el plan aprobado.
- **Tester:** crea y ejecuta las pruebas previstas, y reporta resultados reproducibles.

## Flujo obligatorio

1. El orquestador analiza el requisito y el código relacionado.
2. Consulta a los subagentes de exploración o dominio necesarios.
3. Crea un plan de implementación.
4. Solicita aprobación de Rubén antes de modificar código.
5. Tras la aprobación, entrega al `coder` un handoff completo.
6. El orquestador verifica que la implementación respete el plan y avisa a Rubén.
7. Rubén clasifica el feedback:
   - **Bloqueante:** vuelve al `coder` o al orquestador si cambia el diseño.
   - **Necesario:** vuelve al `coder` para corregirlo.
   - **Opcional:** se documenta y no bloquea la entrega salvo aprobación expresa.
8. Cuando Rubén aprueba la implementación, se invoca al `tester`.
9. El orquestador revisa el reporte del `tester` y coordina correcciones si hay fallos.
10. Se ejecutan las validaciones finales y Rubén realiza la aceptación final.

No se debe implementar antes de aprobar el plan. No se debe declarar terminado un cambio sin validaciones y aceptación final.

## Commits

- Después de completar cada tarea del archivo `tasks.md`, ejecutar las validaciones definidas para esa tarea y crear un commit independiente.
- Todos los commits deben respetar Conventional Commits, usando el tipo y alcance más específico disponible; por ejemplo: `feat(backend): bootstrap Laravel API`.
- No mezclar varias tareas en un mismo commit.
- No incluir en commits ni mantener bajo seguimiento estas rutas y archivos: `.claude/`, `.github/`, `openspec/`, `CGPT-PLAN_IMPLEMENTACION_PRUEBA_TECNICA.md`, `prueba técnica.md` y `workflow.md`.
- Revisar `git status` y el diff antes de cada commit para confirmar que solo contiene los archivos de la tarea actual.

## Contenido mínimo del plan

- Objetivo y contexto.
- Requisitos funcionales y no funcionales.
- Criterios de aceptación verificables.
- Archivos permitidos para modificar o crear.
- Funciones, clases, componentes, rutas o migraciones previstas.
- Responsabilidad de cada elemento.
- Dependencias y contratos afectados.
- Riesgos y decisiones técnicas.
- Exclusiones explícitas.
- Estrategia de pruebas y comandos de validación.

## Contrato de handoff

Cada subagente recibe objetivo, contexto, plan, criterios de aceptación, archivos permitidos, restricciones, exclusiones, decisiones, riesgos y comandos esperados. Su respuesta debe indicar cambios realizados, validaciones, fallos y trabajo pendiente.

Si aparece trabajo fuera del alcance, una nueva dependencia, un cambio de contrato o una decisión arquitectónica, el subagente se detiene y lo reporta. El orquestador solicita aprobación antes de continuar.

## Testing

- Cada requisito debe tener trazabilidad hacia una prueba o una justificación documentada.
- Unit tests cubren reglas puras y casos límite.
- Integration/feature tests cubren endpoints, persistencia y contratos.
- E2E se limita al camino feliz cuando sea necesario.
- El reporte incluye comandos exactos, resultados, cobertura disponible y defectos.

## Gate de cierre

El cambio está listo únicamente cuando:

- Los criterios de aceptación están cubiertos.
- Lint, tests, build y validaciones definidas pasan.
- El diff fue revisado contra el plan.
- No hay secretos ni cambios fuera de alcance.
- Los riesgos pendientes están documentados.
- Rubén dio aceptación final.
