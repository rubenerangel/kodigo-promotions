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

### Aplicacion concreta: el target exclusivo

Los tres puntos siguientes son la aplicacion verificable de los principios anteriores, y en cada uno se anota la alternativa que se descarto.

- **El target se modela con objetos polimorficos.** Una interfaz `PromotionTarget` y dos implementaciones instanciables, `ProductTarget` y `CategoryTarget`, cada una responsable de aportar sus propias columnas. *Alternativa descartada:* un DTO con `product_id` y `category_id` nullable mas una guarda que lance excepcion si no viene exactamente uno. *Motivo:* con objetos de target el estado invalido deja de ser representable, en lugar de ser representable y obligar a defenderse de el en cada caso de uso que toque el target. Los casos de uso no contienen condicionales por variante, de modo que anadir un tercer tipo de target no obliga a modificarlos.
- **Los casos de uso reciben sus colaboradores por constructor y persisten con `newQuery()`.** *Alternativa descartada:* llamar a los metodos estaticos del modelo, como `Promotion::create()`. *Consecuencia:* el contenedor resuelve los casos de uso por autowiring, sin bindings manuales, y las pruebas pueden sustituir colaboradores.
- **Los conflictos de estado se senalizan con una jerarquia de excepciones de dominio.** Una base abstracta `PromotionStateConflict` sobre `DomainException`, y una subclase concreta por regla. *Alternativas descartadas:* una unica excepcion concreta que se distinga por el texto del mensaje, y lanzar directamente una excepcion HTTP de Symfony desde el caso de uso. *Motivo:* la capa HTTP mapea unicamente la clase base a `409`, de modo que anadir una regla de estado no obliga a tocar el mapeo, y cada regla conserva un tipo propio que las pruebas pueden afirmar sin depender de cadenas. Lanzar la excepcion HTTP habria sido mas corto, pero acopla el dominio al transporte y deja los casos de uso inservibles desde un comando de consola o una cola.
- **Los DTO de entrada no exponen constructores desde peticion HTTP.** *Alternativa descartada:* metodos tipo `fromRequest()` o `fromArray()` en el propio DTO. *Motivo:* convertir una peticion en datos de dominio pertenece a la capa de Form Requests; mantenerlo fuera evita que el transporte se filtre al dominio.

Criterio para este documento: se registra una decision cuando existio una alternativa real que se descarto, y se anota cual fue. No se registran estados transitorios de la implementacion, porque dejan de ser ciertos y convierten el documento en un changelog desactualizado.

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
- Las migraciones se ejecutan en el entrypoint del backend porque `migrate --force` es idempotente.
- El catalogo de productos y categorias tambien se siembra en el arranque, con `db:seed --force` justo despues de migrar. Es seguro porque los seeders usan `firstOrCreate`: arranques repetidos no duplican filas ni consumen secuencias de id. El motivo es que el formulario de promociones necesita targets seleccionables; sin catalogo, un `docker compose up` desde volumen limpio deja la aplicacion inusable para quien la evalua.
- El entrypoint genera `APP_KEY` solo cuando no existe ni en el entorno ni en el `.env` del contenedor. Compose siempre inyecta la variable, vacia cuando no se define, asi que comprobar unicamente el entorno regeneraba la clave en cada reinicio. Ademas la variable vacia se descarta con `unset` antes de arrancar, porque el Dotenv de Laravel nunca sobrescribe una variable ya presente y una cadena vacia ganaria sobre el valor del `.env`. Efecto lateral conocido: los comandos lanzados con `docker compose exec` heredan esa variable vacia, de modo que un `artisan` que dependa de cifrado debe ejecutarse con `unset APP_KEY` previo.
- La base `promotions_test` que consume la suite se crea mediante un script de inicializacion montado en `/docker-entrypoint-initdb.d` del contenedor de PostgreSQL. La imagen lo ejecuta solo al inicializar un cluster nuevo, de modo que un ciclo limpio (`down -v` y `up`) deja el entorno listo para ejecutar las pruebas sin pasos manuales.

### Frontend en Docker: artefacto estatico, no dev server

- La imagen del frontend se construye en dos etapas: Node ejecuta `pnpm build` y nginx sirve el contenido de `dist/`.
- Se descarta correr el dev server de Vite dentro del contenedor porque Compose existe para reproducibilidad y evaluacion (`docker compose up` y el smoke test de CI), no para el desarrollo diario, que se hace con `pnpm dev` contra `php artisan serve` en el host.
- *Consecuencia:* `VITE_API_URL` se resuelve en tiempo de construccion, se pasa como build arg y cambiarla exige reconstruir la imagen del frontend; no hay HMR dentro del contenedor.
- *Beneficio:* imagen pequena sin Node en ejecucion, `tsc -b` actua como puerta de calidad del build, y CI valida el mismo artefacto que se desplegaria.
- Se descarto la variante con proxy `/api` en nginx porque ocultaria la validacion de CORS, que el flujo local si necesita.

### Pruebas contra PostgreSQL, no SQLite en memoria

- La suite se ejecuta contra PostgreSQL. `phpunit.xml` fija `DB_CONNECTION=pgsql` y `DB_DATABASE=promotions_test`; el host, el puerto, el usuario y la contrasena siguen viniendo de `.env` para no versionar credenciales.
- *Motivo:* el esquema de `promotions` depende de restricciones `CHECK` con nombre, que SQLite no soporta via `ALTER TABLE ... ADD CONSTRAINT`. Ademas queremos que las pruebas validen el mismo motor que produccion: constraints reales, semantica `DATE` real y agregaciones reales.
- *Contrapartida:* ejecutar las pruebas exige un PostgreSQL disponible en local y un servicio de Postgres en CI, y obliga a crear la base `promotions_test` antes del primer `php artisan test`.

### Health check del contenedor backend

- El healthcheck del contenedor backend apunta temporalmente a `/up`, el endpoint nativo de Laravel, porque `/health` se implementa en la tarea 3.7.
- Debe migrarse a `/health` en esa tarea.

## 6. Seguridad y alcance

- No se versionan contraseñas, claves, tokens ni archivos `.env`; `.env.example` contiene solo nombres y valores locales no sensibles.
- Se excluyen del seguimiento `.claude/`, `.github/`, `openspec/`, `CGPT-PLAN_IMPLEMENTACION_PRUEBA_TECNICA.md`, `prueba técnica.md` y `workflow.md`.
