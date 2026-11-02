#!/usr/bin/env bash
# Integral del Este — despliega una versión nueva (Arquitectura, sección 7).
#   ./deploy.sh v1.0.1      (una etiqueta o rama del repositorio)
# Pasos: copia de la base, código nuevo, imagen, migraciones, reemplazo de app y worker,
# control de /salud y vuelta atrás automática si algo falla.
set -euo pipefail
cd "$(dirname "$0")"

VERSION="${1:?Uso: ./deploy.sh <etiqueta-o-rama>}"
ANTERIOR="$(git -C .. rev-parse --short HEAD)"
LOG=deploys.log
source .env
URL="${PUBLIC_URL:-https://$DOMINIO}"

echo "== Desplegando $VERSION (antes: $ANTERIOR)"

echo "-- 1. Copia de la base antes de cambiar nada"
docker compose run --rm backup /backup.sh || { echo "La copia falló: no se despliega."; exit 1; }

echo "-- 2. Código y imagen nuevos"
git -C .. fetch --tags origin
git -C .. checkout --quiet "$VERSION"
export APP_TAG="$VERSION"
docker compose build app

volver() {
  echo "!! Falló el despliegue: vuelvo a $ANTERIOR"
  git -C .. checkout --quiet "$ANTERIOR"
  export APP_TAG="$ANTERIOR"
  docker compose build app && docker compose up -d --no-deps app worker
  echo "$(date -Iseconds) $(whoami) FALLÓ $VERSION, volvió a $ANTERIOR" >> "$LOG"
  exit 1
}

echo "-- 3. Migraciones de base (compatibles hacia atrás)"
docker compose run --rm migrar || volver

echo "-- 4. Reemplazo de app y worker"
docker compose up -d --no-deps app worker || volver

echo "-- 5. Control de funcionamiento"
for i in $(seq 1 20); do
  if curl -fsS -m 5 "$URL/salud" | grep -q '"ok":true'; then
    echo "$(date -Iseconds) $(whoami) $VERSION OK (antes $ANTERIOR)" >> "$LOG"
    echo "== Listo: $VERSION funcionando."
    exit 0
  fi
  sleep 3
done
volver
