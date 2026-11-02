-- =====================================================================
-- Lotes de prueba · L04 · Autorizaciones y auditoría (CU-20, CU-21)
-- =====================================================================
--   · 2 solicitudes "En auditoría" (aviso al ingresar y franja roja)
--   · 1 autorización emitida hoy por Belén
--   · 1 rechazo por cuotas impagas (Vilar Ivan)
-- La numeración de autorizaciones arranca en 1001.
-- =====================================================================

INSERT INTO autorizaciones (afiliado_id, prestacion_cod, fecha, estado, observacion, controles, usuario, usuario_id)
SELECT a.id, 'B01', current_date, 'pendiente', 'El médico indicó 10 sesiones por una lesión.',
       '[{"ok": true, "t": "Afiliado activo", "d": "N° 501"}, {"ok": true, "t": "Carencia", "d": "Óptica y biobárica: sin carencia."}, {"ok": true, "t": "Pagos al día", "d": "Sin cuotas vencidas."}]',
       'Belén', (SELECT id FROM usuarios WHERE nombre_norm = 'belen')
FROM afiliados a WHERE a.nro = 501;

INSERT INTO autorizaciones (afiliado_id, prestacion_cod, fecha, estado, observacion, controles, usuario, usuario_id)
SELECT a.id, 'E01', current_date, 'pendiente', 'Estudio de alta complejidad: pide excepción.',
       '[{"ok": false, "t": "Carencia", "d": "Estudios y prácticas: requiere 90 días desde el alta. Lleva 30, faltan 60."}]',
       'Cecilia', (SELECT id FROM usuarios WHERE nombre_norm = 'cecilia')
FROM afiliados a WHERE a.nro = 507;

INSERT INTO autorizaciones (afiliado_id, prestacion_cod, fecha, estado, consumo, usuario, usuario_id)
SELECT a.id, 'C01', current_date, 'autorizada', 'sin cargo', 'Belén', (SELECT id FROM usuarios WHERE nombre_norm = 'belen')
FROM afiliados a WHERE a.nro = 505;

INSERT INTO autorizaciones (afiliado_id, prestacion_cod, fecha, estado, motivo, usuario, usuario_id)
SELECT a.id, 'C01', current_date - 1, 'rechazada', 'Cuotas impagas', 'Mari', (SELECT id FROM usuarios WHERE nombre_norm = 'mari')
FROM afiliados a WHERE a.nro = 68;
