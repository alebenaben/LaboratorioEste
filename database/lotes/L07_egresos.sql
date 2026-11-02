-- =====================================================================
-- Lotes de prueba · L07 · Egresos y contabilidad (CU-33, CU-34)
-- =====================================================================
--   · Alquiler fijo del mes pasado (Factura A $ 12.100 con IVA $ 2.100):
--     este mes aparece en "Gastos fijos sin cargar".
--   · Internet de este mes (Factura B, sin IVA discriminado).
--   · Librería en efectivo.
-- =====================================================================

INSERT INTO egresos (fecha, categoria, proveedor, cuit, detalle, comprobante, numero, importe, iva, medio, fijo, usuario) VALUES
 ((date_trunc('month', current_date) - interval '1 month')::date + 4, 'alquiler', 'Inmobiliaria del Este', '30-12345678-9', 'Alquiler de la oficina', 'Factura A', '0002-00000123', 12100, 2100, 'Transferencia bancaria', true, 'Santiago'),
 (date_trunc('month', current_date)::date, 'servicios', 'Telecom', '30-63945373-8', 'Internet de la oficina', 'Factura B', '0010-00456789', 18500, 0, 'Débito automático', true, 'Santiago'),
 (current_date, 'insumos', 'Librería San Martín', '', 'Resmas y tóner', 'Ticket', '', 9800, 0, 'Efectivo', false, 'Katy');
