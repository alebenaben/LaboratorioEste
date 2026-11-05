#!/bin/sh
# Integral del Este — arranque del contenedor "todo en uno" (Dockerfile de la raíz).
#
# 1. Resuelve la conexión a la base desde DATABASE_URL (o DB_*), con las claves
#    internas de integral_app e integral_backup derivadas de la del dueño.
# 2. Prepara el certificado de ARCA si llegó como variable (ARCA_CERT_B64 / ARCA_KEY_B64).
# 3. Aplica las migraciones (y los lotes de prueba si CARGAR_LOTES=1 fuera de producción).
# 4. Quita del entorno la clave del dueño y arranca con supervisord: Apache (web),
#    el worker (CAE y mails) y, si están configuradas, las copias de seguridad.
#
# Con un comando como argumento (docker run imagen <comando>) lo ejecuta con el entorno listo.
set -e
APP_DIR="${APP_DIR:-/var/www/integral}"
cd "$APP_DIR"
export APP_DIR

log() { echo "{\"nivel\":\"info\",\"msg\":\"$*\"}"; }

# Si faltan los datos de la base, entorno.php explica qué falta y se termina acá.
ENTORNO="$(php bin/entorno.php)" || exit 1
eval "$ENTORNO"

# --- ARCA: certificado y clave en base64 (alternativa a montar los archivos) ---
ARCA_DIR="$APP_DIR/storage/arca"
for par in "ARCA_CERT_B64:arca.crt:ARCA_CERT_FILE" "ARCA_KEY_B64:arca.key:ARCA_KEY_FILE"; do
  var="${par%%:*}"; resto="${par#*:}"; archivo="${resto%%:*}"; destino="${resto#*:}"
  eval "valor=\${$var:-}"
  if [ -n "$valor" ]; then
    mkdir -p "$ARCA_DIR"
    printf '%s' "$valor" | base64 -d > "$ARCA_DIR/$archivo"
    chown www-data:www-data "$ARCA_DIR/$archivo" 2>/dev/null || true
    chmod 600 "$ARCA_DIR/$archivo"
    export "$destino=$ARCA_DIR/$archivo"
    unset "$var"
  fi
done

# --- Mail: si hay servidor SMTP y no se eligió modo, se usa SMTP ---
if [ -z "${MAIL_MODO:-}" ]; then
  if [ -n "${SMTP_HOST:-}" ]; then MAIL_MODO=smtp; else MAIL_MODO=archivo; fi
  export MAIL_MODO
fi

# --- Comando puntual ---
if [ "$#" -gt 0 ]; then
  exec "$@"
fi

# --- Migraciones ---
log "aplicando migraciones"
php bin/migrar.php
if [ "${CARGAR_LOTES:-0}" = "1" ] && [ "${APP_ENV:-produccion}" != "produccion" ]; then
  php bin/migrar.php --lotes-si-faltan
fi

# --- Copias: se activan solo si están los datos del bucket ---
COPIAS_ACTIVAS=false
if [ -n "${RESTIC_REPOSITORY:-}" ] && [ -n "${RESTIC_PASSWORD:-}${RESTIC_PASSWORD_FILE:-}" ] \
   && [ -n "${AWS_ACCESS_KEY_ID:-}" ] && [ -n "${AWS_SECRET_ACCESS_KEY:-}${AWS_SECRET_ACCESS_KEY_FILE:-}" ]; then
  COPIAS_ACTIVAS=true
fi
export COPIAS_ACTIVAS
log "copias de seguridad: $([ "$COPIAS_ACTIVAS" = true ] && echo activas || echo 'desactivadas (faltan RESTIC_REPOSITORY, RESTIC_PASSWORD, AWS_ACCESS_KEY_ID o AWS_SECRET_ACCESS_KEY)')"

# La aplicación y el worker no necesitan la clave del dueño de la base.
unset DATABASE_URL DB_OWNER_PASSWORD DB_OWNER_PASSWORD_FILE

log "iniciando web, worker y copias (ARCA: ${ARCA_MODO:-simulado}, mail: $MAIL_MODO)"
exec supervisord -n -c "${SUPERVISOR_CONF:-/etc/supervisor/integral.conf}"
