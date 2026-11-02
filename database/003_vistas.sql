-- =====================================================================
-- Integral del Este — 003 · Plan de cuentas y vistas
-- =====================================================================

-- CU-34: plan de cuentas (imputable = false para los rubros)
CREATE TABLE cuentas (
  codigo     text PRIMARY KEY,
  nombre     text NOT NULL,
  imputable  boolean NOT NULL,
  orden      int NOT NULL
);

-- CU-33: categorías de egresos y su cuenta contable
CREATE TABLE categorias_egreso (
  clave   text PRIMARY KEY,
  nombre  text NOT NULL,
  cuenta  text NOT NULL REFERENCES cuentas(codigo),
  orden   int NOT NULL
);

-- ---------------------------------------------------------------------
-- RT-05: estado de cuenta de cada grupo
--   deuda vencida  = alguna factura pendiente con vencimiento < hoy
--   cuota por vencer = saldo > 0 sin vencidas
--   al día         = el resto
-- ---------------------------------------------------------------------
CREATE VIEW v_estado_cuenta AS
SELECT g.id AS grupo_id,
       COALESCE(f.facturado, 0) - COALESCE(r.cobrado, 0)                         AS saldo,
       COALESCE(f.vencidas, 0)                                                   AS vencidas,
       COALESCE(f.pendientes, 0)                                                 AS pendientes,
       CASE WHEN COALESCE(f.vencidas, 0) > 0 THEN 'vencida'
            WHEN COALESCE(f.facturado, 0) - COALESCE(r.cobrado, 0) > 0 THEN 'por_vencer'
            ELSE 'al_dia' END                                                    AS estado
FROM grupos g
LEFT JOIN (
  SELECT grupo_id,
         sum(importe) AS facturado,
         count(*) FILTER (WHERE estado = 'pendiente' AND vto < current_date) AS vencidas,
         count(*) FILTER (WHERE estado = 'pendiente') AS pendientes
  FROM facturas GROUP BY grupo_id
) f ON f.grupo_id = g.id
LEFT JOIN (SELECT grupo_id, sum(importe) AS cobrado FROM recibos GROUP BY grupo_id) r ON r.grupo_id = g.id;

-- ---------------------------------------------------------------------
-- CU-34: asientos automáticos, calculados (P-11). Una fila por línea.
--   Factura de cuota      1.3 Cuotas a cobrar      / 4.1 Cuotas de afiliados
--   Recibo en efectivo    1.1 Caja                 / 1.3
--   Recibo otro medio     1.2 Banco y billeteras   / 1.3
--   Egreso                5.x (neto) + 1.4 IVA     / 1.1 o 1.2
-- ---------------------------------------------------------------------
CREATE VIEW v_asientos AS
SELECT 'F' || f.id AS asiento, f.emision AS fecha, 1 AS orden_tipo, f.id AS ref,
       'Factura ' || f.letra || ' ' || lpad(f.pv::text, 5, '0') || '-' || lpad(f.numero::text, 8, '0')
         || ' · ' || (f.receptor->>'nombre') || ' · ' || f.periodo AS detalle,
       l.cuenta, l.debe, l.haber, l.linea
FROM facturas f
CROSS JOIN LATERAL (VALUES ('1.3', f.importe, 0::numeric, 1), ('4.1', 0::numeric, f.importe, 2)) AS l(cuenta, debe, haber, linea)
UNION ALL
SELECT 'R' || r.id, r.fecha, 2, r.id,
       'Recibo R' || lpad(r.pv::text, 5, '0') || '-' || lpad(r.numero::text, 8, '0') || ' · ' || r.medio,
       l.cuenta, l.debe, l.haber, l.linea
FROM recibos r
CROSS JOIN LATERAL (VALUES (CASE WHEN r.medio = 'Efectivo' THEN '1.1' ELSE '1.2' END, r.importe, 0::numeric, 1),
                           ('1.3', 0::numeric, r.importe, 2)) AS l(cuenta, debe, haber, linea)
UNION ALL
SELECT 'E' || e.id, e.fecha, 3, e.id,
       'Egreso · ' || ce.nombre || ' · ' || e.proveedor
         || CASE WHEN e.numero <> '' THEN ' · ' || e.comprobante || ' ' || e.numero ELSE '' END,
       l.cuenta, l.debe, l.haber, l.linea
FROM egresos e
JOIN categorias_egreso ce ON ce.clave = e.categoria
CROSS JOIN LATERAL (VALUES (ce.cuenta, e.importe - e.iva, 0::numeric, 1),
                           ('1.4', e.iva, 0::numeric, 2),
                           (CASE WHEN e.medio = 'Efectivo' THEN '1.1' ELSE '1.2' END, 0::numeric, e.importe, 3)) AS l(cuenta, debe, haber, linea)
WHERE l.debe <> 0 OR l.haber <> 0;

GRANT SELECT ON ALL TABLES IN SCHEMA public TO integral_app, integral_backup;

-- ---------------------------------------------------------------------
-- RT-06 / CU-35: ahorro de cada línea de uso. Si la unidad informó un
-- precio se usa el ahorro guardado; si no, se calcula con el valor de
-- referencia vigente de la prestación (los usos sin ninguno de los dos
-- no suman ahorro).
-- ---------------------------------------------------------------------
CREATE VIEW v_usos_lineas AS
SELECT l.*, u.fecha, u.hora, u.afiliado_id, u.grupo_id, u.plan_id, u.unidad_id, u.numero, u.usuario,
       u.autorizacion_id, u.pendiente_unidad,
       CASE WHEN l.lista > 0 THEN l.ahorro
            ELSE round(p.valor_ref * (l.gratis + (l.cantidad - l.gratis) * l.pct / 100)) END AS ahorro_ef,
       (l.lista = 0 AND p.valor_ref = 0 AND (l.gratis > 0 OR l.pct > 0)) AS sin_valor
FROM usos_lineas l
JOIN usos u ON u.id = l.uso_id
JOIN prestaciones p ON p.cod = l.prestacion_cod;

GRANT SELECT ON v_usos_lineas TO integral_app, integral_backup;
