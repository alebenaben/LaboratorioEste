-- =====================================================================
-- Integral del Este — Permisos de los roles (se vuelve a aplicar SIEMPRE)
-- =====================================================================
-- bin/migrar.php corre este archivo al final de cada ejecución, después
-- de 001_roles.sql (que crea los roles o les vuelve a poner la clave).
-- No es una migración: no se registra en schema_migraciones.
-- Así, volver a correr el migrador:
--   · pone las claves nuevas de integral_app e integral_backup (rotación);
--   · recrea los roles y sus permisos después de restaurar una copia en
--     un servidor nuevo (pg_dump no incluye los roles).
-- Tiene que coincidir con los permisos de 002_esquema.sql y 003_vistas.sql.
-- =====================================================================

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO integral_app;
REVOKE UPDATE, DELETE, TRUNCATE ON historial FROM integral_app;
REVOKE INSERT, UPDATE, DELETE ON schema_migraciones FROM integral_app;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO integral_app;

GRANT SELECT ON ALL TABLES IN SCHEMA public TO integral_backup;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO integral_backup;
GRANT INSERT ON backups_estado TO integral_backup;
GRANT USAGE ON SEQUENCE backups_estado_id_seq TO integral_backup;
