-- =====================================================================
-- Lotes de prueba · L03 · Beneficios (RT-06, CU-20 a CU-25)
-- =====================================================================
-- Afiliados de prueba (N° 501 en adelante) con antigüedades medidas
-- desde HOY, para que los criterios valgan cualquier día que se prueben:
--   501 Plus, 7 meses           → hiperbárica SIN CARGO, "le quedan 5 de 5"
--   502 Plus, 8 meses (+503 hijo) → ya usó 4 sesiones sin cargo hoy: registrar 2
--                                  deja 1 sin cargo y 1 con 50 %
--   504 Integral, 89 días       → óptica 10 %
--   505 Integral, 90 días       → óptica 20 %
--   506 Integral, 400 días      → ya usó 6 consultas con 30 % este año (tope 6)
--   507 Plus, 30 días           → en carencia
--   508 Plus, dado de baja
--   509 Plus, 4 meses           → en retención no se ofrece el 20 %
--   510 Integral, 400 días      → no tiene el beneficio de gimnasio (solo Plus)
-- Valores de referencia para calcular el ahorro.
-- =====================================================================

UPDATE prestaciones SET valor_ref = 12000 WHERE cod = 'B01';
UPDATE prestaciones SET valor_ref = 18500 WHERE cod = 'C01';
UPDATE prestaciones SET valor_ref = 20000 WHERE cod = 'C02';
UPDATE prestaciones SET valor_ref = 90000 WHERE cod = 'O01';
UPDATE prestaciones SET valor_ref = 15000 WHERE cod = 'L01';

-- Función auxiliar del lote: crea un grupo con su titular.
CREATE TEMP TABLE _af (nro int, apellido text, nombre1 text, plan text, cat text, dias int, estado text, doc text, grupo_de int);
INSERT INTO _af VALUES
 (501, 'PRUEBA', 'SIETE', 'PLUS', 'TIT', 210, 'activo', '90000501', NULL),
 (502, 'PRUEBA', 'OCHO', 'PLUS', 'TIT', 240, 'activo', '90000502', NULL),
 (503, 'PRUEBA', 'OCHOHIJO', 'PLUS', 'HIJ', 240, 'activo', '90000503', 502),
 (504, 'OPTICA', 'OCHENTANUEVE', 'INT', 'TIT', 89, 'activo', '90000504', NULL),
 (505, 'OPTICA', 'NOVENTA', 'INT', 'TIT', 90, 'activo', '90000505', NULL),
 (506, 'TOPE', 'CONSULTAS', 'INT', 'TIT', 400, 'activo', '90000506', NULL),
 (507, 'CARENCIA', 'RECIENTE', 'PLUS', 'TIT', 30, 'activo', '90000507', NULL),
 (508, 'BAJA', 'PRUEBA', 'PLUS', 'TIT', 300, 'baja', '90000508', NULL),
 (509, 'CUATRO', 'MESES', 'PLUS', 'TIT', 120, 'activo', '90000509', NULL),
 (510, 'INTEGRAL', 'GIMNASIO', 'INT', 'TIT', 400, 'activo', '90000510', NULL);

INSERT INTO grupos (plan_id, forma_pago, email, fecha_alta)
SELECT plan, 'Efectivo', lower(nombre1) || '@prueba.example', current_date - dias FROM _af WHERE grupo_de IS NULL ORDER BY nro;

-- Cada titular va a su grupo (en el mismo orden en que se crearon).
WITH g AS (SELECT id, row_number() OVER (ORDER BY id) AS n FROM grupos WHERE email LIKE '%@prueba.example'),
     t AS (SELECT *, row_number() OVER (ORDER BY nro) AS n FROM _af WHERE grupo_de IS NULL)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, fecha_baja)
SELECT t.nro, g.id, 0, t.apellido, t.nombre1, t.apellido || ' ' || t.nombre1, lower(t.apellido || ' ' || t.nombre1),
       to_char(t.doc::bigint, 'FM99G999G999'), t.doc, t.cat, (SELECT precio FROM precios WHERE plan_id = t.plan AND categoria_id = t.cat),
       current_date - t.dias, t.estado, CASE WHEN t.estado = 'baja' THEN current_date - 10 END
FROM t JOIN g ON g.n = t.n;

INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado)
SELECT f.nro, a.grupo_id, 1, f.apellido, f.nombre1, f.apellido || ' ' || f.nombre1, lower(f.apellido || ' ' || f.nombre1),
       to_char(f.doc::bigint, 'FM99G999G999'), f.doc, f.cat, (SELECT precio FROM precios WHERE plan_id = f.plan AND categoria_id = f.cat), current_date - f.dias, 'activo'
FROM _af f JOIN afiliados a ON a.nro = f.grupo_de WHERE f.grupo_de IS NOT NULL;

UPDATE afiliados SET doc = replace(doc, ',', '.') WHERE nro >= 500;

-- 502: cuatro sesiones de hiperbárica sin cargo usadas hoy (cupo por grupo de 5 al año)
WITH u AS (
  INSERT INTO usos (numero, unidad_id, afiliado_id, grupo_id, plan_id, fecha, usuario)
  SELECT 'U00004-90000001', (SELECT id FROM unidades WHERE nombre = 'Biobárica'), a.id, a.grupo_id, 'PLUS', current_date, 'Biobárica'
  FROM afiliados a WHERE a.nro = 502 RETURNING id)
INSERT INTO usos_lineas (uso_id, prestacion_cod, nombre, cantidad, gratis, pct, precio, precio_informado, lista, ahorro, total)
SELECT u.id, 'B01', 'Sesión de medicina hiperbárica', 4, 4, 0, 12000, true, 48000, 48000, 0 FROM u;

-- 506: seis consultas con otra especialidad con 30 % (máximo 6 por año)
WITH u AS (
  INSERT INTO usos (numero, unidad_id, afiliado_id, grupo_id, plan_id, fecha, usuario)
  SELECT 'U00002-90000001', (SELECT id FROM unidades WHERE nombre = 'CMDE'), a.id, a.grupo_id, 'INT', current_date, 'CMDE'
  FROM afiliados a WHERE a.nro = 506 RETURNING id)
INSERT INTO usos_lineas (uso_id, prestacion_cod, nombre, cantidad, gratis, pct, precio, precio_informado, lista, ahorro, total)
SELECT u.id, 'C02', 'Consulta con otra especialidad', 6, 0, 30, 20000, true, 120000, 36000, 84000 FROM u;

-- Un uso de óptica de Julieta Ramallo con precio informado (para ahorro y estadísticas)
WITH u AS (
  INSERT INTO usos (numero, unidad_id, afiliado_id, grupo_id, plan_id, fecha, usuario)
  SELECT 'U00003-90000001', (SELECT id FROM unidades WHERE nombre = 'Óptica'), a.id, a.grupo_id, 'PLUS', current_date, 'Óptica'
  FROM afiliados a WHERE a.nro = 75 RETURNING id)
INSERT INTO usos_lineas (uso_id, prestacion_cod, nombre, cantidad, gratis, pct, precio, precio_informado, lista, ahorro, total)
SELECT u.id, 'O01', 'Anteojos o lentes', 1, 0, 50, 80000, true, 80000, 40000, 40000 FROM u;

INSERT INTO numeradores (clave, ultimo)
SELECT 'U-' || id, 0 FROM unidades ON CONFLICT (clave) DO NOTHING;

DROP TABLE _af;
