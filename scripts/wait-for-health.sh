#!/usr/bin/env bash
#
# Espera a que la API responda y falla si no llega a hacerlo.
#
# POR QUE UNA PETICION DESDE FUERA Y NO EL HEALTHCHECK DEL CONTENEDOR.
#
# docker-compose.yml ya declara un healthcheck para el backend, y bastaria con
# esperar a que el contenedor se declare `healthy`. Se hace la peticion aparte
# porque no comprueban lo mismo: el healthcheck se ejecuta *dentro* del
# contenedor, contra `localhost:8000`, de modo que pasa igual aunque la
# publicacion del puerto al host este mal. Esta peticion sale del anfitrion y
# entra por el puerto publicado, que es el camino que recorre quien usa la
# aplicacion de verdad.
#
# POR QUE SE SONDEA EN LUGAR DE ESPERAR UNA VEZ.
#
# `docker compose up -d` termina cuando los contenedores arrancan, no cuando
# sirven, y el arranque en frio del backend incluye migraciones y siembra. Una
# sola peticion inmediata fallaria siempre; un `sleep` fijo lo bastante largo
# para no fallar seria lento en todas las ejecuciones y seguiria fallando el dia
# que el runner vaya mas cargado. Se sondea hasta un limite y se falla ahi.
set -euo pipefail

URL="${1:-${HEALTH_URL:-http://localhost:8000/health}}"
TIMEOUT="${2:-${HEALTH_TIMEOUT:-120}}"
INTERVAL=3

echo "wait-for-health: esperando 200 en $URL (limite ${TIMEOUT}s)"

started="$SECONDS"
attempt=0
status=""
body=""

while [ "$((SECONDS - started))" -lt "$TIMEOUT" ]; do
    attempt="$((attempt + 1))"

    # --fail no se usa a proposito: se quiere leer el cuerpo tambien cuando la
    # respuesta es 503, que es justo lo que explica por que no esta listo.
    body="$(curl -sS -o - -w '\n%{http_code}' --max-time 5 "$URL" 2>/dev/null || true)"
    status="$(printf '%s' "$body" | tail -n1)"
    body="$(printf '%s' "$body" | sed '$d')"

    if [ "$status" = "200" ]; then
        echo "wait-for-health: 200 tras $((SECONDS - started))s y $attempt intento(s)"
        echo "wait-for-health: respuesta: $body"
        exit 0
    fi

    sleep "$INTERVAL"
done

echo "wait-for-health: sin 200 tras ${TIMEOUT}s y $attempt intento(s)" >&2
echo "wait-for-health: ultimo codigo del sondeo: ${status:-(sin respuesta)}" >&2

# Una ultima peticion, esta sin prisa, solo para explicar el fallo.
#
# El sondeo corta a los 5 segundos para poder repetir a menudo, y ese limite se
# come justo la respuesta que mas dice: con PostgreSQL caido, /health tarda
# entre 17 y 48 segundos en contestar 503, porque antes tiene que agotar el
# tiempo de conexion del driver. Sondeando se ve siempre 000, que no distingue
# "no habia nadie escuchando" de "contesto que no alcanza la base de datos", y
# son dos averias que se arreglan de forma distinta. Aqui ya no importa tardar:
# el trabajo ha fallado y lo unico que queda es dejar dicho por que.
echo "wait-for-health: una ultima peticion sin limite corto, para diagnosticar..." >&2
# El error de curl se deja salir por stderr en lugar de capturarlo: mezclado
# con la salida acabaria pegado a la linea del codigo y la volveria ilegible.
final="$(curl -sS -o - -w '\n%{http_code}' --max-time 90 "$URL" || true)"
final_status="$(printf '%s' "$final" | tail -n1)"
final_body="$(printf '%s' "$final" | sed '$d')"

echo "wait-for-health: codigo: ${final_status:-(sin respuesta)}" >&2
echo "wait-for-health: cuerpo: ${final_body:-(vacio)}" >&2
exit 1
