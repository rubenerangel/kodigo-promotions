# Decisiones de diseño

## Arquitectura y stack

- Laravel expone la API HTTP y concentra las reglas de negocio del backend.
- PostgreSQL es la base de datos relacional.
- React con TypeScript y Vite implementa la interfaz web.
- Los controladores permanecen delgados: reciben la solicitud, delegan el caso de uso y construyen la respuesta.
- Las validaciones de entrada viven en Form Requests.
- Actions/Services tienen responsabilidad unica y contienen los casos de uso que requieren coordinacion.
- Se usa Eloquent directamente; no se incorpora un Repository generico sin una necesidad concreta.

## Modelo de dominio

- Las tablas principales son `products`, `categories` y `promotions`.
- Cada promocion tiene un target exclusivo: producto o categoria, nunca ambos.
- Las fechas de inicio y fin usan `DATE`, con vigencia inclusiva.
- Los estados se persisten y solo pueden avanzar por las transiciones estrictas `SCHEDULED -> ACTIVE -> FINISHED`.
- La vigencia calculada por fechas es independiente del estado persistido; una promocion puede no estar vigente hoy aunque su estado sea `ACTIVE`.

## API y errores

- Los errores se expresan mediante codigos HTTP: `422` para validacion, `404` para recursos inexistentes y `409` para conflictos de estado.
- Las promociones `FINISHED` no se editan ni se eliminan; la eliminacion queda limitada a `SCHEDULED`.
- El health check debe verificar la conectividad real con PostgreSQL y responder `200` o `503`.

## Seguridad y alcance

- No se versionan contraseñas, claves, tokens ni archivos `.env`; `.env.example` contiene solo nombres y valores locales no sensibles.
- Se excluyen del seguimiento `.claude/`, `.github/`, `openspec/`, `CGPT-PLAN_IMPLEMENTACION_PRUEBA_TECNICA.md`, `prueba técnica.md` y `workflow.md`.
- Docker Compose y los Dockerfiles quedan fuera de esta tarea y se completaran en la tarea 1.4.