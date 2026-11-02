#!/usr/bin/env bash
# Integral del Este — una copia de la base: pg_dump comprimido, cifrado por restic y subido al bucket externo.
# La corre loop.sh cada 6 horas; deploy.sh la corre antes de cada versión:  docker compose run --rm backup /backup.sh
set -euo pipefail

export PGPASSWORD="$(cat "${DB_PASSWORD_FILE}")"
export RESTIC_PASSWORD_FILE AWS_ACCESS_KEY_ID
export AWS_SECRET_ACCESS_KEY="$(cat "${AWS_SECRET_ACCESS_KEY_FILE}")"
export RESTIC_CACHE_DIR="${RESTIC_CACHE_DIR:-/cache}"
DB_HOST="${DB_HOST:-db}"; DB_NAME="${DB_NAME:-integral}"; DB_USER="${DB_USER:-integral_backup}"

registrar() { # ok detalle
  psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -q -v ok="$1" -v det="$2" \
    -c "INSERT INTO backups_estado (ok, origen, detalle) VALUES (:'ok'::boolean, 'automatica', :'det')" || true
}

# Inicializa el repositorio la primera vez.
restic cat config >/dev/null 2>&1 || restic init

if pg_dump -h "$DB_HOST" -U "$DB_USER" -Fc -Z 6 "$DB_NAME" \
   | restic backup --stdin --stdin-filename integral.dump --tag pg --host integral; then
  # La retención se aplica una vez por día (en la copia de las 00 h).
  if [ "$(date +%H)" = "00" ]; then
    # shellcheck disable=SC2086
    restic forget --prune --tag pg ${BACKUP_RETENTION:---keep-last 4 --keep-daily 14 --keep-weekly 8 --keep-monthly 24}
  fi
  # Verificación semanal de una parte de los datos (domingos).
  if [ "$(date +%u)" = "7" ] && [ "$(date +%H)" = "00" ]; then
    restic check --read-data-subset=5% || { registrar false "restic check falló"; exit 1; }
  fi
  registrar true "Copia subida a ${RESTIC_REPOSITORY%%/*}…"
  [ -n "${HEARTBEAT_BACKUP_URL:-}" ] && curl -fsS -m 10 "$HEARTBEAT_BACKUP_URL" >/dev/null || true
  echo "Copia correcta: $(date -Iseconds)"
else
  registrar false "pg_dump o restic falló"
  [ -n "${HEARTBEAT_BACKUP_URL:-}" ] && curl -fsS -m 10 "$HEARTBEAT_BACKUP_URL/fail" >/dev/null || true
  echo "La copia FALLÓ: $(date -Iseconds)" >&2
  exit 1
fi
