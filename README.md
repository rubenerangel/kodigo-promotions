# Promotion Management

Aplicacion web para gestionar promociones de productos y categorias, con reglas explicitas de target, fechas, descuentos y estados.

Las decisiones de diseno y sus alternativas descartadas estan en [DECISIONS.md](DECISIONS.md).

## Stack

- **Backend:** Laravel 13 sobre PHP 8.4, API HTTP sin autenticacion.
- **Persistencia:** PostgreSQL 17.
- **Frontend:** React 19 con TypeScript 6 y Vite 8, construido con pnpm 11 sobre Node 22 y servido por nginx.

## Arranque con Docker

Es la forma prevista de evaluar el proyecto. Desde un clon limpio:

```bash
docker compose up --build
```

**No hace falta crear ningun `.env`:** todas las variables que interpola `docker-compose.yml` declaran valor por defecto. Copia `.env.example` a `.env` solo si necesitas cambiar alguna, por ejemplo un puerto ocupado.

Al levantarse, el servicio `backend` espera a que PostgreSQL supere su healthcheck, aplica las migraciones, siembra el catalogo de productos y categorias, y expone la API. El `frontend` sirve el build estatico de Vite mediante nginx.

Para detener:

```bash
docker compose down      # conserva los datos
docker compose down -v   # borra tambien el volumen de PostgreSQL
```

## URLs

| Que | Donde |
| --- | --- |
| Interfaz | http://localhost:5173 |
| API | http://localhost:8000/api |
| Health check | http://localhost:8000/health |
| PostgreSQL desde el host | `localhost:5432` (o el `POSTGRES_PORT` que definas) |

El health check responde `200` cuando la API alcanza la base de datos y `503` cuando no; consulta PostgreSQL de verdad, no solo la configuracion.

## Variables de entorno

Todas viven en el `.env` de la raiz, se documentan en `.env.example` y **todas tienen valor por defecto**. `scripts/check-env.sh` comprueba que ninguna variable interpolada por el compose file se quede sin documentar.

| Variable | Para que | Por defecto |
| --- | --- | --- |
| `POSTGRES_DB` | Nombre de la base de datos | `promotions` |
| `POSTGRES_USER` | Usuario de PostgreSQL | `postgres` |
| `POSTGRES_PASSWORD` | Contrasena de PostgreSQL | `postgres` |
| `POSTGRES_PORT` | Puerto publicado al host | `5432` |
| `BACKEND_PORT` | Puerto publicado de la API | `8000` |
| `FRONTEND_PORT` | Puerto publicado de la interfaz | `5173` |
| `APP_KEY` | Clave de Laravel | vacia: el entrypoint genera una |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Entorno de Laravel | `local`, `true`, `http://localhost:8000` |
| `DB_*` | Conexion del backend | derivadas de las `POSTGRES_*` |
| `FRONTEND_URL` | Origen que acepta CORS | `http://localhost:5173` |
| `VITE_API_URL` | API que consulta la interfaz | `http://localhost:8000/api` |

`VITE_API_URL` se incrusta **en tiempo de construccion**: cambiarla exige `docker compose build frontend`.

## Comandos

Backend, desde `backend/`:

```bash
php artisan test                                       # suite completa contra PostgreSQL
vendor/bin/pint --test                                 # estilo
vendor/bin/phpstan analyse --memory-limit=1G           # analisis estatico
```

Frontend, desde `frontend/`:

```bash
pnpm lint     # oxlint --deny-warnings: cualquier aviso rompe la construccion
pnpm build    # tsc -b y build de produccion
pnpm dev      # servidor de desarrollo en http://localhost:5173
```

Los mismos comandos, en el mismo orden, son los que ejecuta la integracion continua en `.github/workflows/ci.yml`.

## Desarrollo sin contenedores

El dia a dia se hace con `pnpm dev` contra `php artisan serve`; Compose existe para reproducir y evaluar.

Necesitas PHP 8.4.1 o superior —el `composer.lock` arrastra Symfony 8.1 y `composer install` falla con PHP 8.3—, Composer, Node 22, pnpm y un PostgreSQL accesible.

```bash
cp backend/.env.example backend/.env    # y ajusta DB_HOST, DB_PORT y las credenciales
cd backend && composer install && php artisan key:generate && php artisan migrate --seed && php artisan serve
cd frontend && pnpm install && pnpm dev
```

Para sobrescribir la URL de la API en local, crea `frontend/.env.local` con `VITE_API_URL`.

### La base de datos de pruebas

La suite corre contra PostgreSQL, no contra SQLite en memoria, porque el esquema depende de restricciones `CHECK` con nombre que SQLite no admite. Necesita una base `promotions_test`, que el contenedor crea solo al inicializar un cluster nuevo mediante `docker/postgres/init/01-create-test-db.sql`. Basta con:

```bash
docker compose up -d postgres
```

Si tu volumen `postgres-data` es anterior a ese script, creala una vez a mano:

```bash
docker compose exec postgres createdb -U postgres promotions_test
```

El host, el puerto, el usuario y la contrasena salen de `backend/.env`, nunca de `phpunit.xml`.

## Troubleshooting

**El puerto 5432 ya esta ocupado.** Es el caso mas comun, porque cualquier PostgreSQL local lo tiene tomado: en la maquina donde se desarrollo esto, lo ocupa el que trae Laragon. Define otro puerto en el `.env` de la raiz y vuelve a levantar:

```bash
POSTGRES_PORT=5434
```

**El stack funciona igual aunque 5432 este ocupado**, y conviene entender por que: el backend no usa el puerto publicado, sino que alcanza a `postgres:5432` por la red interna de Compose. El puerto del host solo hace falta para conectarte tu —con `psql`, con un cliente grafico, o para ejecutar `php artisan test` fuera del contenedor—, y en ese caso `backend/.env` tiene que declarar el mismo `DB_PORT`. Si en su lugar apuntas al 5432 ocupado, acabaras hablando con la base de datos equivocada, no con la del proyecto.

Lo mismo vale para `BACKEND_PORT` y `FRONTEND_PORT`.

**Cambiaste `VITE_API_URL` y la interfaz sigue llamando a la anterior.** Vite la incrusta al construir: `docker compose build frontend` y vuelve a levantar.

**El backend tarda en responder al arrancar.** Es lo esperado: no arranca hasta que el healthcheck de PostgreSQL pasa, y en el primer arranque ademas migra y siembra. `docker compose logs -f backend` lo cuenta paso a paso.

**La interfaz no carga datos y el navegador habla de CORS.** El backend solo acepta el origen de `FRONTEND_URL`. Si sirves la interfaz en otro puerto, declara ese mismo origen ahi.

**Datos en mal estado o migraciones a medias.** `docker compose down -v` borra el volumen y el siguiente `up` parte de una base vacia, migrada y sembrada.

**`php artisan test` falla al conectar.** Falta la base `promotions_test`, o `backend/.env` apunta a un host o un puerto que no son los del contenedor. Ver arriba.

**La `APP_KEY` cambia entre un `down` y un `up`.** Es conocido y no tiene consecuencia: el `.env` vive dentro del contenedor y la API no guarda sesiones, cookies ni columnas cifradas. Si necesitas una clave estable, declarala en el `.env` de la raiz y el entrypoint la respeta.

## Reglas del dominio

- Una promocion apunta exclusivamente a un producto o a una categoria, nunca a ambos.
- Las fechas se almacenan como `DATE` y la vigencia es inclusiva por los dos extremos.
- Los estados persistidos avanzan solo por las transiciones estrictas `SCHEDULED -> ACTIVE -> FINISHED`, sin saltos ni vuelta atras.
- La vigencia por fechas es independiente del estado: una promocion `FINISHED` puede seguir vigente hoy, y una `ACTIVE` puede no estarlo.
- Una promocion `FINISHED` no se edita ni se elimina; eliminar queda limitado a `SCHEDULED`.
- Los errores usan `422` para validacion, `404` para lo inexistente y `409` para conflictos de estado, siempre con un codigo legible por maquina.

## Alcance y seguridad

No se versionan contrasenas, claves, tokens ni archivos `.env`; `.env.example` contiene solo nombres y valores locales no sensibles. El proyecto no expone autenticacion: es una prueba tecnica de una sola pantalla.
