-- =====================================================================
-- Lotes de prueba · L01 · Usuarios y datos de contacto del padrón
-- =====================================================================
-- Usuarios: la clave de cada uno es su nombre (por ejemplo Santiago /
-- Santiago, Belén / Belén, Biobárica / Biobárica, sanbenito / sanbenito).
-- En este lote NO se pide cambiarla, para poder probar rápido.
-- Además: emails, teléfonos, fechas de nacimiento (uno cumple hoy) y un
-- grupo que factura como Responsable Inscripto (Factura A).
-- =====================================================================

UPDATE usuarios SET debe_cambiar_clave = false, activo = true;

-- Emails de algunos grupos (los demás quedan sin email → envío por WhatsApp)
UPDATE grupos SET email = 'julieta.ramallo@example.com', sin_email = false WHERE id = (SELECT grupo_id FROM afiliados WHERE nro = 75);
UPDATE grupos SET email = 'mauro.gonzalez@example.com',  sin_email = false WHERE id = (SELECT grupo_id FROM afiliados WHERE nro = 70);
UPDATE grupos SET email = 'dante.goltz@example.com',     sin_email = false WHERE id = (SELECT grupo_id FROM afiliados WHERE nro = 74);
UPDATE grupos SET email = 'mabel.brun@example.com',      sin_email = false WHERE id = (SELECT grupo_id FROM afiliados WHERE nro = 91);

-- Teléfonos (RT-03: 10 dígitos con característica)
UPDATE afiliados SET telefono = '3435013651' WHERE nro = 91;
UPDATE afiliados SET telefono = '03435 15 401234' WHERE nro = 75;
UPDATE afiliados SET telefono = '3434628168' WHERE nro IN (92, 93);
UPDATE afiliados SET telefono = '343 4112233' WHERE nro = 70;

-- Fechas de nacimiento
UPDATE afiliados SET nac = '1981-03-14' WHERE nro = 75;
UPDATE afiliados SET nac = '1979-07-02' WHERE nro = 76;
UPDATE afiliados SET nac = '2010-05-20' WHERE nro = 77;
UPDATE afiliados SET nac = '1958-11-30' WHERE nro = 64;
UPDATE afiliados SET nac = '1986-09-09' WHERE nro = 70;
-- CU-14: Santa Cruz Graciela cumple años hoy (51 años)
UPDATE afiliados SET nac = (current_date - interval '51 years')::date WHERE nro = 80;

-- CU-08 / CU-26: Goltz Dante factura como Responsable Inscripto → Factura A
UPDATE grupos SET fiscal_cond = 'RI', fiscal_cuit = '20-16612302-4', fiscal_razon = 'GOLTZ DANTE'
 WHERE id = (SELECT grupo_id FROM afiliados WHERE nro = 74);
