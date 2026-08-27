# Decisiones de diseño

## 1. Stack

- Laravel expone la API HTTP y concentra las reglas de negocio del backend.
- PostgreSQL es la base de datos relacional.
- React con TypeScript y Vite implementa la interfaz web.

## 2. Arquitectura y principios de diseño

### SOLID en el backend

- **SRP:** cada Action/Service resuelve un unico caso de uso; si una clase necesita dos motivos para cambiar, se divide.
- **OCP y LSP:** las variantes de descuento y de estado se modelan con enums y contratos, de modo que agregar una variante no obliga a hacer crecer condicionales por tipo repartidos en el codigo.
- **ISP:** se definen interfaces pequenas y especificas para cada colaboracion, en lugar de una interfaz de servicio general que obligue a implementar metodos irrelevantes.
- **DIP:** los casos de uso dependen de abstracciones, nunca de implementaciones concretas.

### Inyeccion de dependencias en lugar de estado estatico

- Las dependencias se reciben por constructor y las resuelve el contenedor de servicios de Laravel.
- Queda prohibido implementar logica de negocio en clases estaticas, metodos estaticos o helpers globales, porque impiden sustituir colaboradores en las pruebas, ocultan las dependencias reales de cada clase y crean acoplamiento a implementaciones concretas.
- Se registran bindings de interfaz a implementacion en un Service Provider cuando exista mas de una implementacion o cuando una prueba necesite sustituir un colaborador.

### Distribucion de responsabilidades

- Los controladores permanecen delgados: reciben la solicitud, delegan el caso de uso y construyen la respuesta.
- Las validaciones de entrada viven en Form Requests.
- Actions/Services tienen responsabilidad unica y contienen los casos de uso que requieren coordinacion.
- Se usa Eloquent directamente desde los casos de uso; no se incorpora un Repository generico sin una necesidad concreta.

## 3. Modelo de dominio

- Las tablas principales son `products`, `categories` y `promotions`.
- Cada promocion tiene un target exclusivo: producto o categoria, nunca ambos.
- Las fechas de inicio y fin usan `DATE`, con vigencia inclusiva.
- Los estados se persisten y solo pueden avanzar por las transiciones estrictas `SCHEDULED -> ACTIVE -> FINISHED`.
- La vigencia calculada por fechas es independiente del estado persistido; una promocion puede no estar vigente hoy aunque su estado sea `ACTIVE`.

## 4. API y errores

- Los errores se expresan mediante codigos HTTP: `422` para validacion, `404` para recursos inexistentes y `409` para conflictos de estado.
- Las promociones `FINISHED` no se editan ni se eliminan; la eliminacion queda limitada a `SCHEDULED`.
- El health check debe verificar la conectividad real con PostgreSQL y responder `200` o `503`.

## 5. Entorno y Docker

- Docker Compose define tres servicios: `postgres`, `backend` y `frontend`.
- La imagen del backend usa PHP 8.4 porque el `composer.lock` de Laravel 13 arrastra Symfony 8.1, que exige `php >=8.4.1`; por coherencia, `composer.json` declara `"php": "^8.4"`.
- PostgreSQL declara un healthcheck con `pg_isready`, y el arranque del backend queda condicionado a que ese healthcheck pase, para evitar fallos de conexion en el primer arranque.
- La persistencia usa un volumen nombrado, de modo que `docker compose down` conserva los datos y `docker compose down -v` los descarta.
- Las migraciones se ejecutan en el entrypoint del backend porque `migrate --force` es idempotente. Los seeders no se ejecutan automaticamente.

### Frontend en Docker: artefacto estatico, no dev server

- La imagen del frontend se construye en dos etapas: Node ejecuta `pnpm build` y nginx sirve el contenido de `dist/`.
- Se descarta correr el dev server de Vite dentro del contenedor porque Compose existe para reproducibilidad y evaluacion (`docker compose up` y el smoke test de CI), no para el desarrollo diario, que se hace con `pnpm dev` contra `php artisan serve` en el host.
- *Consecuencia:* `VITE_API_URL` se resuelve en tiempo de construccion, se pasa como build arg y cambiarla exige reconstruir la imagen del frontend; no hay HMR dentro del contenedor.
- *Beneficio:* imagen pequena sin Node en ejecucion, `tsc -b` actua como puerta de calidad del build, y CI valida el mismo artefacto que se desplegaria.
- Se descarto la variante con proxy `/api` en nginx porque ocultaria la validacion de CORS, que el flujo local si necesita.

### Health check del contenedor backend

- El healthcheck del contenedor backend apunta temporalmente a `/up`, el endpoint nativo de Laravel, porque `/health` se implementa en la tarea 3.7.
- Debe migrarse a `/health` en esa tarea.

## 6. Seguridad y alcance

- No se versionan contraseñas, claves, tokens ni archivos `.env`; `.env.example` contiene solo nombres y valores locales no sensibles.
- Se excluyen del seguimiento `.claude/`, `.github/`, `openspec/`, `CGPT-PLAN_IMPLEMENTACION_PRUEBA_TECNICA.md`, `prueba técnica.md` y `workflow.md`.
