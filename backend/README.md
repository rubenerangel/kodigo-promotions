# Backend

API HTTP de gestion de promociones: Laravel 13 sobre PHP 8.4, contra PostgreSQL 17. Sin autenticacion.

La documentacion del proyecto completo —requisitos previos, arranque con Docker, variables y troubleshooting— esta en el [README de la raiz](../README.md), y las decisiones de diseno en [DECISIONS.md](../DECISIONS.md).

## Comandos

```bash
composer install
php artisan migrate --seed                    # esquema y catalogo de productos y categorias
php artisan serve                             # API en http://localhost:8000
php artisan test                              # suite completa, contra PostgreSQL
vendor/bin/pint --test                        # estilo
vendor/bin/phpstan analyse --memory-limit=1G  # analisis estatico (Larastan)
```

La suite necesita una base `promotions_test`; el README de la raiz explica como se crea.

## Endpoints

| Metodo | Ruta | Que hace |
| --- | --- | --- |
| `GET` | `/health` | Consulta PostgreSQL de verdad: `200` o `503` |
| `GET` | `/api/promotions` | Listado con el target ya resuelto y la vigencia de hoy |
| `POST` | `/api/promotions` | Crea una promocion, siempre en `SCHEDULED` |
| `PATCH` | `/api/promotions/{id}` | Sustituye los datos editables; exige el cuerpo completo |
| `DELETE` | `/api/promotions/{id}` | Elimina, solo si esta en `SCHEDULED` |
| `PATCH` | `/api/promotions/{id}/status` | Mueve el ciclo de vida un paso |
| `GET` | `/api/promotions/summary` | Contadores por estado y vigentes hoy, en una sola consulta |
| `GET` | `/api/promotion-targets` | Catalogo de productos y categorias para el formulario |

Los errores viajan como `{"error": {"code", "message", "fields"}}`, con `fields` solo cuando el fallo es del payload. El `message` va en ingles a proposito: es una interfaz de maquina, y el texto que lee la persona lo pone el frontend a partir del `code`.

## Convenciones

- Un caso de uso por Action, en `app/Actions/Promotions`, con sus colaboradores por constructor.
- La validacion de entrada vive en Form Requests; los controladores solo delegan y responden.
- Las reglas de estado se senalizan con excepciones de dominio bajo `PromotionStateConflict`, que la capa HTTP traduce a `409`.
- Nada de logica de negocio en metodos estaticos ni en helpers globales.
