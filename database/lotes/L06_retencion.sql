-- =====================================================================
-- Lotes de prueba · L06 · Retención de bajas (CU-12, CU-13)
-- =====================================================================
--   · Godoy Cielo (N° 69): "lo va a pensar" hace 3 días → aparece en "Para llamar hoy"
--   · Aguilar Marisa (N° 71): derivada a administración (pide 10 %)
--   · Gonzalez Angela (N° 72): insiste en la baja → la confirma un administrador
--   · Alterini Benjamín (N° 77): se quedó hace 100 días → su grupo no recibe
--     descuentos en la cuota otra vez (365 días)
-- =====================================================================

INSERT INTO retenciones (fecha, afiliado_id, grupo_id, motivo, comentario, ofertas, resultado, llamar, usuario)
SELECT current_date - 3, a.id, a.grupo_id, 'nouso', 'No sabía que tenía enfermería', '[{"k":"valor","t":"Recordarle todo lo que ya tiene","res":"no"}]', 'piensa', current_date, 'Belén'
FROM afiliados a WHERE a.nro = 69;

INSERT INTO retenciones (fecha, afiliado_id, grupo_id, motivo, comentario, ofertas, resultado, aceptada, usuario)
SELECT current_date - 1, a.id, a.grupo_id, 'economico', 'Perdió el trabajo', '[{"k":"valor","t":"Recordarle todo lo que ya tiene","res":"no"}]', 'derivado', '10% de descuento en la cuota por 3 meses', 'Cecilia'
FROM afiliados a WHERE a.nro = 71;

INSERT INTO retenciones (fecha, afiliado_id, grupo_id, motivo, comentario, ofertas, resultado, usuario)
SELECT current_date, a.id, a.grupo_id, 'mudanza', 'Se va a vivir a Córdoba', '[{"k":"valor","t":"Recordarle todo lo que ya tiene","res":"no"}]', 'baja_pedida', 'Angie'
FROM afiliados a WHERE a.nro = 72;

INSERT INTO retenciones (fecha, afiliado_id, grupo_id, motivo, comentario, ofertas, resultado, aceptada, usuario, cerrado)
SELECT current_date - 100, a.id, a.grupo_id, 'economico', '', '[{"k":"valor","t":"Recordarle todo lo que ya tiene","res":"no"},{"k":"d10","t":"10% de descuento en la cuota por 3 meses","res":"acepto"}]', 'retenido', '10% de descuento en la cuota por 3 meses', 'Santiago', current_date - 100
FROM afiliados a WHERE a.nro = 77;
