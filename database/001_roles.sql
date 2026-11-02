-- =====================================================================
-- Integral del Este — 001 · Roles de base de datos
-- =====================================================================
-- Se ejecuta UNA vez, conectado como el dueño de la base (usuario
-- "integral", el POSTGRES_USER del contenedor) o como superusuario.
--
--   integral         dueño del esquema; lo usan las migraciones.
--   integral_app     la aplicación y el worker: leen y escriben datos,
--                    no pueden crear ni borrar tablas, y sobre el
--                    historial solo pueden agregar y leer.
--   integral_backup  solo lectura, para pg_dump.
--
-- Las claves NO están en este archivo: bin/migrar.php reemplaza
-- __APP_PASSWORD__ y __BACKUP_PASSWORD__ con el contenido de los
-- secretos (DB_APP_PASSWORD_FILE y DB_BACKUP_PASSWORD_FILE).
-- Para correrlo a mano:
--   sed -e "s/__APP_PASSWORD__/$(cat secrets/db_app_password)/" \
--       -e "s/__BACKUP_PASSWORD__/$(cat secrets/db_backup_password)/" \
--       database/001_roles.sql | psql -U integral -d integral
-- =====================================================================

DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'integral_app') THEN
    CREATE ROLE integral_app LOGIN PASSWORD '__APP_PASSWORD__';
  ELSE
    ALTER ROLE integral_app LOGIN PASSWORD '__APP_PASSWORD__';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'integral_backup') THEN
    CREATE ROLE integral_backup LOGIN PASSWORD '__BACKUP_PASSWORD__';
  ELSE
    ALTER ROLE integral_backup LOGIN PASSWORD '__BACKUP_PASSWORD__';
  END IF;
END
$$;

-- Nadie más que el dueño puede crear objetos en el esquema public.
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO integral_app, integral_backup;
