#!/usr/bin/env bash
# Corre /backup.sh en las horas de BACKUP_HORAS (por defecto 0,6,12,18). Si se le pasa un comando, lo ejecuta.
set -uo pipefail
if [ "$#" -gt 0 ]; then exec "$@"; fi
HORAS=",${BACKUP_HORAS:-0,6,12,18},"
ULTIMA=""
echo "Servicio de copias iniciado (horas: ${BACKUP_HORAS:-0,6,12,18})"
while true; do
  H=$((10#$(date +%H)))
  CLAVE="$(date +%F)-$H"
  if [[ "$HORAS" == *",$H,"* ]] && [ "$(date +%M)" -lt 10 ] && [ "$CLAVE" != "$ULTIMA" ]; then
    /backup.sh && ULTIMA="$CLAVE" || ULTIMA="$CLAVE"
  fi
  sleep 60
done
