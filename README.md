# Promotion Management

Aplicacion web para gestionar promociones de productos y categorias, con reglas explicitas de target, fechas, descuentos y estados.

## Stack

- Backend: Laravel 13, PHP 8.4 y API HTTP.
- Persistencia: PostgreSQL.
- Frontend: React 19, TypeScript y Vite.

## Prerrequisitos

- Docker y Docker Compose, para levantar el stack completo de forma reproducible.
- Para desarrollo local sin contenedores: PHP 8.4+, Composer y una instancia local de PostgreSQL. El `composer.lock` fija dependencias de Laravel 13 que arrastran Symfony 8.1 y exigen PHP 8.4.1 o superior, asi que `composer install` falla con PHP 8.3.
- Node.js compatible con las versiones declaradas y pnpm o npm.

## Ejecucion con Docker

1. Copia `.env.example` a `.env` en la raiz del repositorio y ajusta solo los valores locales.
2. Levanta el stack:

```bash
docker compose up --build
```

3. Para detener los contenedores conservando los datos:

```bash
docker compose down
```

4. Para detener los contenedores y borrar el volumen de datos de PostgreSQL:

```bash
docker compose down -v
```

URLs resultantes:

- Frontend: http://localhost:5173
- Backend: http://localhost:8000
- PostgreSQL: localhost:5432

El servicio `backend` espera a que PostgreSQL supere su healthcheck, aplica las migraciones y expone la API. El servicio `frontend` sirve el build estatico de Vite mediante nginx.

## Troubleshooting minimo

- **Puertos ocupados:** ajusta `BACKEND_PORT`, `FRONTEND_PORT` o `POSTGRES_PORT` en el `.env` de la raiz y vuelve a ejecutar `docker compose up`.
- **Cambiaste `VITE_API_URL`:** Vite incrusta esa variable en tiempo de construccion, asi que debes reconstruir la imagen con `docker compose build frontend` antes de volver a levantar el stack.
- **El backend parece tardar en arrancar:** es esperado, no arranca hasta que el healthcheck de PostgreSQL pasa.
- **Datos corruptos o migraciones en mal estado:** ejecuta `docker compose down -v` para borrar el volumen y levanta el stack de nuevo desde una base de datos vacia.

## Configuracion local

1. Copia `.env.example` a `backend/.env` y, si necesitas sobreescribir variables del frontend, crea `frontend/.env.local` con `VITE_API_URL`.
2. Completa solo valores locales en esos archivos. No guardes credenciales ni claves en Git.
3. En `backend/`, instala dependencias, genera la clave de Laravel y ejecuta las migraciones cuando existan:

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

4. En `frontend/`, instala dependencias y arranca Vite:

```bash
pnpm install
pnpm dev
```

## Comandos previstos

Backend, desde `backend/`:

```bash
php artisan test
vendor/bin/pint
```

La suite de pruebas se ejecuta contra PostgreSQL, no contra SQLite en memoria, porque el esquema depende de restricciones `CHECK` que SQLite no soporta. Requiere una base de datos `promotions_test` disponible. Para crearla con el PostgreSQL de Compose:

```bash
docker compose up -d postgres
docker compose exec postgres createdb -U postgres promotions_test
```

Sin esa base de datos, `php artisan test` falla al conectar. El host, el puerto, el usuario y la contrasena se leen de `backend/.env`, nunca de `phpunit.xml`.

Frontend, desde `frontend/`:

```bash
pnpm lint
pnpm build
```

## URLs locales

- Frontend Vite: http://localhost:5173
- Backend Laravel: http://localhost:8000
- API: http://localhost:8000/api
- Health check previsto: http://localhost:8000/health

## Reglas principales

- Una promocion apunta exclusivamente a un producto o a una categoria.
- Las fechas son inclusivas y se almacenan como `DATE`.
- Los estados persistidos siguen las transiciones estrictas `SCHEDULED -> ACTIVE -> FINISHED`.
- La vigencia por fecha es independiente del estado persistido.
- Las respuestas de error usan codigos HTTP coherentes con el tipo de fallo.

## Alcance y seguridad

La implementacion prioriza una entrega pequena y verificable. Los secretos, archivos de entorno locales y documentos de trabajo excluidos se mantienen fuera del seguimiento. Las decisiones de arquitectura y dominio estan en [DECISIONS.md](DECISIONS.md).
