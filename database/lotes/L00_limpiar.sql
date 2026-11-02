-- =====================================================================
-- Lotes de prueba · L00 · Volver al punto de partida
-- =====================================================================
-- SOLO para bases de prueba o demostración. Borra todo lo cargado sobre
-- el padrón inicial (facturas, cobros, usos, autorizaciones, egresos…)
-- y los afiliados de prueba (N° 500 en adelante), para que los lotes
-- L01 a L08 se puedan volver a cargar cuantas veces haga falta.
--   php bin/migrar.php --lotes
-- =====================================================================

DELETE FROM usos_lineas;
DELETE FROM usos;
DELETE FROM autorizaciones;
DELETE FROM consumos_comercio;
DELETE FROM recibos;
DELETE FROM facturas;
DELETE FROM retenciones;
DELETE FROM saludos_cumple;
DELETE FROM resumenes_enviados;
DELETE FROM promociones_envios;
DELETE FROM promociones WHERE titulo <> 'Llegó el verano';
DELETE FROM consultas;
DELETE FROM egresos;
DELETE FROM cola_tareas;
DELETE FROM avisos;
DELETE FROM config_cambios;
DELETE FROM sesiones;
DELETE FROM intentos_ingreso;
DELETE FROM tokens_admin;
DELETE FROM afiliados WHERE nro >= 500;
DELETE FROM grupos g WHERE NOT EXISTS (SELECT 1 FROM afiliados a WHERE a.grupo_id = g.id);
DELETE FROM numeradores;
INSERT INTO numeradores (clave, ultimo) VALUES ('FB-1', 0), ('FA-1', 0), ('R-1', 0);
DELETE FROM config WHERE clave LIKE 'arca_ta%' OR clave = 'ultima_emision';
UPDATE config SET valor = '{"B": 0, "A": 0}' WHERE clave = 'ultimos_numeros';
UPDATE config SET valor = '{"cuotas": 1}' WHERE clave = 'punto_venta';
UPDATE config SET valor = '10' WHERE clave = 'dia_vto';
UPDATE config SET valor = '{"editar": true, "descuentos": true, "montos": true, "padron": true}' WHERE clave = 'permisos';
UPDATE rubros_comercio SET limite = 0, per = 'anio';
SELECT setval('afiliado_nro_seq', greatest(93, (SELECT coalesce(max(nro), 93) FROM afiliados WHERE nro < 500)));
