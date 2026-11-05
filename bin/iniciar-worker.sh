#!/bin/sh
# Integral del Este — arranque del worker con las migraciones primero.
# Pensado para EasyPanel (Deploy → Command: sh bin/iniciar-worker.sh), donde no
# hay un servicio "migrar" aparte. Necesita DB_OWNER_USER / DB_OWNER_PASSWORD.
# Si una migración falla, el contenedor termina con error y no arranca el worker.
set -e
cd "$(dirname "$0")/.."
php bin/migrar.php
exec php bin/worker.php "$@"
