-- =====================================================================
-- Integral del Este — Estado inicial ("de fábrica")
-- =====================================================================
-- Deja la base como recién instalada:
--   · sin afiliados ni grupos, y sin nada que haga referencia a ellos
--     (facturas, recibos, autorizaciones, usos, consumos, retenciones,
--     saludos, consultas, envíos de promociones y de resúmenes);
--   · sin datos operativos (egresos, historial, cambios de configuración,
--     avisos, cola de tareas, estado de copias, promociones);
--   · con un único usuario: admin / admin (administrador, todas las
--     secciones). Tiene que cambiar la clave en el primer ingreso;
--   · numeración de afiliados, autorizaciones y comprobantes desde cero,
--     y configuración de facturación (ARCA, últimos números) reiniciada.
--
-- Se MANTIENEN los catálogos: unidades, planes, categorías, precios,
-- prestaciones, reglas de beneficio, beneficios del plan, folleto,
-- condiciones, rubros de comercio, cuentas, categorías de egreso y la
-- configuración general (empresa, inicio, carencias, textos de mail).
--
-- IRREVERSIBLE: hacer una copia antes (pg_dump).
-- No es una migración: bin/migrar.php no lo ejecuta. Correrlo a mano
-- como el DUEÑO del esquema (usuario "integral"); integral_app no puede
-- borrar el historial:
--   psql -U integral -d integral -v ON_ERROR_STOP=1 -f database/estado_inicial.sql
-- Con Docker:
--   docker compose exec -T db psql -U integral -d integral -v ON_ERROR_STOP=1 < database/estado_inicial.sql
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 1. Afiliados, grupos, usuarios y todo lo que depende de ellos.
--    Un solo TRUNCATE con todas las tablas relacionadas: no quedan filas
--    huérfanas y RESTART IDENTITY reinicia los id y las secuencias propias
--    (afiliado_nro_seq vuelve a 1, autorizacion_nro_seq a 1001).
-- ---------------------------------------------------------------------
TRUNCATE TABLE
  -- beneficios
  usos_lineas,
  usos,
  autorizaciones,
  consumos_comercio,
  -- caja
  recibos,
  facturas,
  resumenes_enviados,
  -- comercial y retención
  promociones_envios,
  promociones,
  consultas,
  retenciones,
  saludos_cumple,
  -- padrón
  afiliados,
  grupos,
  -- usuarios y seguridad
  sesiones,
  tokens_admin,
  intentos_ingreso,
  usuarios,
  -- gestión y control
  egresos,
  config_cambios,
  historial,
  avisos,
  cola_tareas,
  backups_estado
RESTART IDENTITY;

-- ---------------------------------------------------------------------
-- 2. Numeración de comprobantes y configuración de facturación
-- ---------------------------------------------------------------------
DELETE FROM numeradores;
INSERT INTO numeradores (clave, ultimo) VALUES ('FB-1', 0), ('FA-1', 0), ('R-1', 0);

-- Ticket de acceso de ARCA (se vuelve a pedir solo) y última emisión masiva
DELETE FROM config WHERE clave LIKE 'arca_ta%' OR clave = 'ultima_emision';

UPDATE config SET valor = '{"B": 0, "A": 0}', actualizado_por = 'sistema' WHERE clave = 'ultimos_numeros';
UPDATE config SET valor = '{"cuotas": 1}', actualizado_por = 'sistema' WHERE clave = 'punto_venta';
UPDATE config SET valor = '10', actualizado_por = 'sistema' WHERE clave = 'dia_vto';
UPDATE config SET valor = '{"editar": true, "descuentos": true, "montos": true, "padron": true}', actualizado_por = 'sistema' WHERE clave = 'permisos';
UPDATE config SET valor = '{"fecha": null, "usuario": null}', actualizado_por = 'sistema' WHERE clave = 'precios_actualizados';

-- ---------------------------------------------------------------------
-- 3. Usuario de fábrica: admin / admin
--    Clave en Argon2id (igual que Usuarios::hash). debe_cambiar_clave
--    obliga a ponerle una clave propia en el primer ingreso.
-- ---------------------------------------------------------------------
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, activo, secciones, debe_cambiar_clave)
VALUES (
  'admin',
  'admin',
  '$argon2id$v=19$m=65536,t=4,p=1$SjZielJKaWtML0kvbU1lag$ldbld0fld9Sj5T2vd/yH9qcwNxN8Bd6K9EAgR7JquNM',
  'admin',
  true,
  '{"verificar","inicio","afiliados","altas","cotizador","autorizaciones","auditoria","prestaciones","beneficios","retencion","caja","egresos","informes","cuentas","contabilidad","estadisticas","promociones","config"}'::text[],
  true
);

-- ---------------------------------------------------------------------
-- 4. Primer registro del historial
-- ---------------------------------------------------------------------
INSERT INTO historial (accion, detalle, usuario)
VALUES ('Sistema', 'Base de datos llevada al estado inicial (sin afiliados, usuario admin)', 'consola');

COMMIT;
