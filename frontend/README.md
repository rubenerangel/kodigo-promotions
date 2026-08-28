# Frontend

Interfaz web de gestión de promociones: React 19, TypeScript y Vite.

La documentación del proyecto completo, incluidos los requisitos previos, el arranque con Docker y las decisiones de diseño, está en el [README de la raíz](../README.md) y en [DECISIONS.md](../DECISIONS.md).

## Comandos

```bash
pnpm install
pnpm dev      # servidor de desarrollo en http://localhost:5173
pnpm build    # comprobación de tipos con tsc y build de producción
pnpm lint     # oxlint
pnpm preview  # sirve el build de producción
```

## Variables de entorno

`VITE_API_URL` apunta a la API. Su valor por defecto es `http://localhost:8000/api`, definido en `src/services/api.ts`. Para sobreescribirlo en local, crea `frontend/.env.local`.

Vite incrusta esta variable **en tiempo de construcción**, así que cambiarla exige reconstruir: `pnpm build`, o `docker compose build frontend` si trabajas con el stack en contenedores.

## Convenciones

- Todo el texto visible sale del catálogo en `src/copy/es.ts`. Los mensajes de error de la API llegan en inglés y no se muestran nunca: se traducen a partir de su `code`. El motivo está registrado en `DECISIONS.md`.
- Los colores, espaciados y radios salen de las variables de `src/styles/tokens.css`.
- Ningún estado se comunica solo por color: siempre lleva texto que lo acompañe.
