#!/usr/bin/env bash
#
# Comprueba la configuracion que consume Docker Compose antes de levantar nada.
#
# POR QUE NO UNA LISTA DE VARIABLES ESCRITA A MANO.
#
# La forma obvia de "validar las variables requeridas" es enumerarlas en un
# array y comprobar que estan definidas. Esa lista es una segunda copia de algo
# que ya esta en docker-compose.yml, y se queda atras en cuanto alguien anade
# una variable: el script sigue en verde mientras deja de comprobar justo lo
# ultimo que se toco. Aqui las variables se extraen del propio compose file, de
# modo que no hay nada que mantener sincronizado.
#
# QUE SIGNIFICA "REQUERIDA" EN ESTE PROYECTO.
#
# Hoy todas las interpolaciones del compose file declaran un valor por defecto
# con `:-`, asi que un clon limpio arranca sin ningun `.env`, y eso esta
# comprobado. La consecuencia es que una variable ausente ya no produce el error
# poco claro que se temia al disenar esto: produce algo peor, un arranque
# silencioso con un valor que nadie eligio. De ahi las dos comprobaciones:
#
#   1. Toda variable sin valor por defecto es obligatoria y tiene que llegar por
#      entorno. Hoy no hay ninguna; el dia que se anada, este script la exige
#      sin que haya que acordarse de actualizarlo.
#   2. Toda variable que el compose file interpola tiene que estar documentada
#      en .env.example. Es la comprobacion que de verdad se dispara: sin ella,
#      anadir `${NUEVA_VAR}` al compose file y olvidar documentarla deja a quien
#      copia .env.example con un valor por defecto que no sabe que existe.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT/docker-compose.yml"
ENV_EXAMPLE="$ROOT/.env.example"

for file in "$COMPOSE_FILE" "$ENV_EXAMPLE"; do
    if [ ! -f "$file" ]; then
        echo "check-env: falta $file" >&2
        exit 1
    fi
done

# Cada aparicion de ${NOMBRE, con el operador que la sigue si lo hay. Se examina
# aparicion por aparicion y no expresion por expresion, que es lo que permite
# leer correctamente las anidadas como ${DB_DATABASE:-${POSTGRES_DB:-promotions}}.
occurrences="$(grep -oE '\$\{[A-Za-z_][A-Za-z0-9_]*(:?-)?' "$COMPOSE_FILE" | sed 's/^\${//' | sort -u)"

documented="$(grep -oE '^[A-Za-z_][A-Za-z0-9_]*=' "$ENV_EXAMPLE" | sed 's/=$//' | sort -u)"

# Listas separadas por saltos de linea en lugar de arrays: se leen igual, se
# imprimen sin ceremonia y no obligan a rodear cada uso con las expansiones
# defensivas que exige `set -u` con un array vacio.
required=""
undocumented=""

add_once() {
    # $1 lista, $2 nombre. Devuelve la lista con el nombre si no estaba.
    if printf '%s\n' "$1" | grep -qx "$2"; then
        printf '%s' "$1"
    else
        printf '%s\n%s' "$1" "$2" | sed '/^$/d'
    fi
}

for occurrence in $occurrences; do
    case "$occurrence" in
        *:-|*-)
            # Tiene valor por defecto: opcional. No se acumula porque nada se
            # comprueba sobre ella mas alla de estar documentada.
            name="${occurrence%%[:-]*}"
            ;;
        *)
            name="$occurrence"
            # Una variable que aparece con valor por defecto en un sitio y sin
            # el en otro se trata como obligatoria: basta un uso sin respaldo
            # para que su ausencia importe.
            required="$(add_once "$required" "$name")"
            ;;
    esac

    if ! printf '%s\n' "$documented" | grep -qx "$name"; then
        undocumented="$(add_once "$undocumented" "$name")"
    fi
done

status=0
total="$(printf '%s\n' "$occurrences" | grep -c . || true)"
echo "check-env: $total interpolaciones en docker-compose.yml"

if [ -n "$undocumented" ]; then
    status=1
    echo "check-env: variables interpoladas pero no documentadas en .env.example:" >&2
    printf '  - %s\n' $undocumented >&2
    echo "check-env: anadelas a .env.example, o quien copie ese archivo arrancara con un valor por defecto que no sabe que existe." >&2
fi

missing=""
for name in $required; do
    if [ -z "${!name:-}" ]; then
        missing="$(add_once "$missing" "$name")"
    fi
done

if [ -n "$missing" ]; then
    status=1
    echo "check-env: variables sin valor por defecto y sin definir en el entorno:" >&2
    printf '  - %s\n' $missing >&2
fi

if [ "$status" -eq 0 ]; then
    echo "check-env: todas las variables interpoladas estan documentadas."
    if [ -z "$required" ]; then
        # Se dice en voz alta en lugar de callarlo: que no haya obligatorias es
        # un hecho del compose file, no una comprobacion que se haya saltado.
        echo "check-env: ninguna variable es obligatoria; todas declaran valor por defecto."
    else
        # Sin comillas a proposito: el troceado en palabras convierte la lista
        # de una por linea en una sola linea separada por espacios.
        echo "check-env: obligatorias presentes:" $required
    fi
fi

exit "$status"
