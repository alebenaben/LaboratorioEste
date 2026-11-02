-- =====================================================================
-- Lotes de prueba · L02 · Cobranza (RT-05)
-- =====================================================================
-- Facturas de un punto de venta de prueba (9) para no mezclar la
-- numeración con la que emite el sistema (punto de venta 1):
--   · Vilar Ivan (N° 68):        cuota VENCIDA hace 30 días → "Deuda vencida"
--   · Santa Cruz Graciela (N° 80): cuota que vence en 5 días → "Cuota por vencer"
--   · Goltz Dante (N° 74):       Factura A pagada con recibo → "Al día"
-- =====================================================================

INSERT INTO facturas (letra, pv, numero, grupo_id, plan_id, periodo, emision, vto, importe, base, alicuota, receptor, lineas, cae, cae_vto, cae_estado, estado, usuario)
SELECT 'B', 9, 1, a.grupo_id, 'PLUS', to_char(current_date - 40, 'YYYY-MM'), current_date - 40, current_date - 30, 45000, 45000, 10.5,
       jsonb_build_object('cond', 'CF', 'nombre', a.nombre, 'doc', 'DNI ' || a.doc),
       jsonb_build_array(jsonb_build_object('nombre', 'VILAR IVAN', 'nro', 68, 'cat', 'TIT', 'cuota', 25000), jsonb_build_object('nombre', 'GODOY CIELO', 'nro', 69, 'cat', 'CON', 'cuota', 20000)),
       '76000000000001', current_date - 30, 'autorizada', 'pendiente', 'lote'
FROM afiliados a WHERE a.nro = 68;

INSERT INTO facturas (letra, pv, numero, grupo_id, plan_id, periodo, emision, vto, importe, base, alicuota, receptor, lineas, cae, cae_vto, cae_estado, estado, usuario)
SELECT 'B', 9, 2, a.grupo_id, 'PLUS', to_char(current_date, 'YYYY-MM'), current_date - 5, current_date + 5, 25000, 25000, 10.5,
       jsonb_build_object('cond', 'CF', 'nombre', a.nombre, 'doc', 'DNI ' || a.doc),
       jsonb_build_array(jsonb_build_object('nombre', a.nombre, 'nro', a.nro, 'cat', 'TIT', 'cuota', 25000)),
       '76000000000002', current_date + 5, 'autorizada', 'pendiente', 'lote'
FROM afiliados a WHERE a.nro = 80;

INSERT INTO facturas (letra, pv, numero, grupo_id, plan_id, periodo, emision, vto, importe, base, alicuota, receptor, lineas, cae, cae_vto, cae_estado, estado, usuario)
SELECT 'A', 9, 1, a.grupo_id, 'PLUS', to_char(current_date - 25, 'YYYY-MM'), current_date - 25, current_date - 15, 25000, 25000, 10.5,
       jsonb_build_object('cond', 'RI', 'nombre', 'GOLTZ DANTE', 'doc', 'CUIT 20-16612302-4'),
       jsonb_build_array(jsonb_build_object('nombre', a.nombre, 'nro', a.nro, 'cat', 'TIT', 'cuota', 25000)),
       '76000000000003', current_date - 15, 'autorizada', 'pagada', 'lote'
FROM afiliados a WHERE a.nro = 74;

INSERT INTO recibos (pv, numero, factura_id, grupo_id, fecha, importe, medio, usuario)
SELECT 9, 1, f.id, f.grupo_id, current_date - 20, f.importe, 'Efectivo', 'Santiago'
FROM facturas f WHERE f.pv = 9 AND f.letra = 'A' AND f.numero = 1;
