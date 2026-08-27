# Promotion Management

Aplicacion web para gestionar promociones de productos y categorias, con reglas explicitas de target, fechas, descuentos y estados.

## Stack

- Backend: Laravel 13, PHP 8.3 y API HTTP.
- Persistencia: PostgreSQL.
- Frontend: React 19, TypeScript y Vite.

## Prerrequisitos

- PHP 8.3+, Composer y una instancia local de PostgreSQL.
- Node.js compatible con las versiones declaradas y pnpm o npm.
- Docker Compose sera necesario cuando se complete la tarea 1.4. El repositorio aun no incluye un stack Docker ejecutable.

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

Frontend, desde `frontend/`:

```bash
pnpm lint
pnpm build
```

Los comandos de Docker Compose se documentaran y validaran al completar la tarea 1.4.

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