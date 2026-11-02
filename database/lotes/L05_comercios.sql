-- =====================================================================
-- Lotes de prueba · L05 · Comercios adheridos (CU-23, CU-44)
-- =====================================================================
--   · Gimnasio: máximo 15 % (como el folleto), un consumo de Julieta Ramallo hoy.
--   · Barbería: límite de 2 usos por semestre, con 2 usos de Mauro González
--     este semestre → el tercero se rechaza.
-- =====================================================================

UPDATE rubros_comercio SET max_pct = 15 WHERE nombre = 'Gimnasio';
UPDATE rubros_comercio SET limite = 2, per = 'semestre' WHERE nombre = 'Barbería Mauro Leiva';

INSERT INTO consumos_comercio (fecha, usuario_id, comercio, rubro, afiliado_id, detalle, importe, pct, ahorro, total)
SELECT current_date, (SELECT id FROM usuarios WHERE nombre_norm = 'sanbenito'), 'San Benito Energy Fitness', 'Gimnasio', a.id, 'Cuota mensual del gimnasio', 20000, 15, 3000, 17000
FROM afiliados a WHERE a.nro = 75;

INSERT INTO consumos_comercio (fecha, usuario_id, comercio, rubro, afiliado_id, detalle, importe, pct, ahorro, total)
SELECT current_date, (SELECT id FROM usuarios WHERE nombre_norm = 'barberia'), 'Barbería Mauro Leiva', 'Barbería Mauro Leiva', a.id, d, 8000, 20, 1600, 6400
FROM afiliados a, (VALUES ('Corte de pelo'), ('Corte y barba')) AS x(d) WHERE a.nro = 70;
