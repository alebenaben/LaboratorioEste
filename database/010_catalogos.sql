-- =====================================================================
-- Integral del Este — 010 · Catálogos y configuración inicial
-- =====================================================================
-- Valores tomados de la maqueta v82 (folleto, PDF de beneficios, precios
-- vigentes v30, reglas por plan, rubros de comercios, plantillas de mail).
-- =====================================================================

-- Unidades de negocio originales (CU-43)
INSERT INTO unidades (nombre, nombre_largo, servicio, punto_venta, original) VALUES ('CMDE', 'Centro Médico del Este', 'Consultas médicas', 2, true);
INSERT INTO unidades (nombre, nombre_largo, servicio, punto_venta, original) VALUES ('Óptica', 'Óptica', 'Óptica', 3, true);
INSERT INTO unidades (nombre, nombre_largo, servicio, punto_venta, original) VALUES ('Biobárica', 'Biobárica', 'Medicina hiperbárica', 4, true);
INSERT INTO unidades (nombre, nombre_largo, servicio, punto_venta, original) VALUES ('Laboratorio', 'Laboratorio del Este', 'Análisis de laboratorio', 5, true);

-- Planes (CU-45). Alícuota de IVA de las cuotas: 10,5 % (v46).
INSERT INTO planes (id, nombre, individual, alicuota, orden) VALUES ('INT', 'Plan Integral del Este', false, 10.5, 2);
INSERT INTO planes (id, nombre, individual, alicuota, orden) VALUES ('PLUS', 'Plan Plus del Este', false, 10.5, 1);
INSERT INTO planes (id, nombre, individual, alicuota, orden) VALUES ('SALUD', 'Plan Salud', true, 10.5, 3);
INSERT INTO planes (id, nombre, individual, alicuota, orden) VALUES ('SEPELIO', 'Plan Sepelio', true, 10.5, 4);
UPDATE planes SET inferior = 'INT' WHERE id = 'PLUS';
UPDATE planes SET inferior = 'SALUD' WHERE id = 'INT';

-- Categorías
INSERT INTO categorias (id, nombre, titular, orden) VALUES ('TIT', 'Titular', true, 1);
INSERT INTO categorias (id, nombre, titular, orden) VALUES ('TIT64', 'Titular +64 años', true, 2);
INSERT INTO categorias (id, nombre, titular, orden) VALUES ('HIJ', 'Adicional menor de 18', false, 3);
INSERT INTO categorias (id, nombre, titular, orden) VALUES ('CON', 'Adicional 18 a 49 años', false, 4);
INSERT INTO categorias (id, nombre, titular, orden) VALUES ('A50', 'Adicional 50 a 64 años', false, 5);
INSERT INTO categorias (id, nombre, titular, orden) VALUES ('CON64', 'Adicional +64 años', false, 6);

-- Precios vigentes (v30). Precio 0 = el plan no se vende en esa categoría.
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('INT', 'TIT', 15000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('INT', 'TIT64', 19000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('INT', 'HIJ', 11250);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('INT', 'CON', 12000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('INT', 'A50', 13500);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('INT', 'CON64', 19000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('PLUS', 'TIT', 25000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('PLUS', 'TIT64', 29000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('PLUS', 'HIJ', 18750);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('PLUS', 'CON', 20000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('PLUS', 'A50', 22500);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('PLUS', 'CON64', 29000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SALUD', 'TIT', 10000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SALUD', 'TIT64', 10000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SALUD', 'HIJ', 0);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SALUD', 'CON', 0);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SALUD', 'A50', 0);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SALUD', 'CON64', 0);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SEPELIO', 'TIT', 10000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SEPELIO', 'TIT64', 14000);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SEPELIO', 'HIJ', 0);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SEPELIO', 'CON', 0);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SEPELIO', 'A50', 0);
INSERT INTO precios (plan_id, categoria_id, precio) VALUES ('SEPELIO', 'CON64', 0);

-- Prestaciones (CU-40). El valor de referencia sirve para calcular el ahorro (RT-06).
INSERT INTO prestaciones (cod, nombre, tipo, unidad_id, iva, orden) VALUES ('C01', 'Consulta clínica, cardiología o pediatría', 'consulta', (SELECT id FROM unidades WHERE nombre = 'CMDE'), 21, 1);
INSERT INTO prestaciones (cod, nombre, tipo, unidad_id, iva, orden) VALUES ('C02', 'Consulta con otra especialidad', 'consulta', (SELECT id FROM unidades WHERE nombre = 'CMDE'), 21, 2);
INSERT INTO prestaciones (cod, nombre, tipo, unidad_id, iva, orden) VALUES ('N01', 'Enfermería en consultorio', 'consulta', (SELECT id FROM unidades WHERE nombre = 'CMDE'), 21, 3);
INSERT INTO prestaciones (cod, nombre, tipo, unidad_id, iva, orden) VALUES ('N02', 'Enfermería a domicilio', 'consulta', (SELECT id FROM unidades WHERE nombre = 'CMDE'), 21, 4);
INSERT INTO prestaciones (cod, nombre, tipo, unidad_id, iva, orden) VALUES ('E01', 'Estudio o práctica', 'estudio', (SELECT id FROM unidades WHERE nombre = 'CMDE'), 21, 5);
INSERT INTO prestaciones (cod, nombre, tipo, unidad_id, iva, orden) VALUES ('B01', 'Sesión de medicina hiperbárica', 'beneficio', (SELECT id FROM unidades WHERE nombre = 'Biobárica'), 21, 6);
INSERT INTO prestaciones (cod, nombre, tipo, unidad_id, iva, orden) VALUES ('O01', 'Anteojos o lentes', 'beneficio', (SELECT id FROM unidades WHERE nombre = 'Óptica'), 21, 7);
INSERT INTO prestaciones (cod, nombre, tipo, unidad_id, iva, orden) VALUES ('L01', 'Análisis de laboratorio', 'estudio', (SELECT id FROM unidades WHERE nombre = 'Laboratorio'), 21, 8);

-- Reglas de beneficios por plan (reglasIniciales de la maqueta, RT-06)
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('INT', 'C01', 1, 'anio', false, 180, 0, 'fijo', 180, '', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('INT', 'C02', 0, 'anio', false, 0, 30, 'fijo', 180, '', 6);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('INT', 'N01', 1, 'mes', false, 90, 40, 'hasta', 90, '', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('INT', 'N02', 1, 'semestre', false, 90, 40, 'hasta', 90, '', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('INT', 'B01', 1, 'anio', true, 180, 30, 'fijo', 90, '', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('INT', 'O01', 0, 'anio', false, 0, 0, 'hasta', 0, '0:10, 90:20, 180:30, 365:50', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('INT', 'L01', 0, 'anio', false, 0, 30, 'hasta', 180, '', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('PLUS', 'C01', 1, 'anio', false, 180, 0, 'fijo', 180, '', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('PLUS', 'C02', 0, 'anio', false, 0, 30, 'fijo', 180, '', 6);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('PLUS', 'N01', 1, 'mes', false, 90, 40, 'hasta', 90, '', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('PLUS', 'N02', 1, 'semestre', false, 90, 40, 'hasta', 90, '', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('PLUS', 'B01', 5, 'anio', true, 180, 50, 'fijo', 210, '', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('PLUS', 'O01', 0, 'anio', false, 0, 0, 'hasta', 0, '0:10, 90:50', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('PLUS', 'L01', 0, 'anio', false, 0, 30, 'hasta', 180, '', 0);
INSERT INTO reglas_beneficio (plan_id, prestacion_cod, gratis, per, por_grupo, car_gratis, descuento, modo, car, escala, max_desc) VALUES ('PLUS', 'E01', 0, 'anio', false, 0, 20, 'hasta', 90, '', 0);

-- Rubros de comercios adheridos (CU-44)
INSERT INTO rubros_comercio (nombre, max_pct, limite, per, orden) VALUES ('Gimnasio', 15, 0, 'anio', 1);
INSERT INTO rubros_comercio (nombre, max_pct, limite, per, orden) VALUES ('Leguan', 10, 0, 'anio', 2);
INSERT INTO rubros_comercio (nombre, max_pct, limite, per, orden) VALUES ('Tiendas de indumentaria', 20, 0, 'anio', 3);
INSERT INTO rubros_comercio (nombre, max_pct, limite, per, orden) VALUES ('Barbería Mauro Leiva', 20, 0, 'anio', 4);
INSERT INTO rubros_comercio (nombre, max_pct, limite, per, orden) VALUES ('Laboratorio', 30, 0, 'anio', 5);
INSERT INTO rubros_comercio (nombre, max_pct, limite, per, orden) VALUES ('Farmacia', 30, 0, 'anio', 6);
INSERT INTO rubros_comercio (nombre, max_pct, limite, per, orden) VALUES ('Odontología', 50, 0, 'anio', 7);
INSERT INTO rubros_comercio (nombre, max_pct, limite, per, orden) VALUES ('Óptica', 50, 0, 'anio', 8);
INSERT INTO rubros_comercio (nombre, max_pct, limite, per, orden) VALUES ('Viajes turísticos y hotelería', 0, 0, 'anio', 9);
INSERT INTO rubros_comercio (nombre, max_pct, limite, per, orden) VALUES ('Electrodomésticos y productos diversos', 0, 0, 'anio', 10);

-- Beneficios detallados por plan (CU-16). '=' de la maqueta se resolvió al texto del Plan Integral.
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (1, 'Consultas médicas', 'PLUS', 'Una (1) consulta médica anual sin costo (sin coseguro ni copago), con orden médica, en Medicina Clínica, Cardiología y Pediatría (control de niño sano). Las demás especialidades tienen 30% de descuento, con una consulta cada dos (2) meses y un máximo de seis (6) por año.', '6 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (1, 'Consultas médicas', 'INT', 'Una (1) consulta médica anual sin costo (sin coseguro ni copago), con orden médica, en Medicina Clínica, Cardiología y Pediatría (control de niño sano). Las demás especialidades tienen 30% de descuento, con una consulta cada dos (2) meses y un máximo de seis (6) por año.', '6 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (2, 'Consulta médica virtual', 'PLUS', 'Video consultas médicas. Para pedir turno, el afiliado escribe por WhatsApp al 3435446701 del Centro Médico del Este.', 'Sin carencia');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (2, 'Consulta médica virtual', 'INT', 'Video consultas médicas. Para pedir turno, el afiliado escribe por WhatsApp al 3435446701 del Centro Médico del Este.', 'Sin carencia');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (3, 'Enfermería', 'PLUS', 'Una (1) prestación mensual sin costo en consultorio, dentro del horario habitual. Atención a domicilio incluida una (1) vez cada seis (6) meses, sujeta a disponibilidad. Control de enfermería sin restricción dentro del horario habitual y procedimientos simples hasta una (1) vez por mes. No incluye insumos descartables: los aporta el paciente o el prestador con cargo. Las prestaciones adicionales tienen descuentos progresivos del 20%, 30% y 40% según el servicio.', '3 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (3, 'Enfermería', 'INT', 'Una (1) prestación mensual sin costo en consultorio, dentro del horario habitual. Atención a domicilio incluida una (1) vez cada seis (6) meses, sujeta a disponibilidad. Control de enfermería sin restricción dentro del horario habitual y procedimientos simples hasta una (1) vez por mes. No incluye insumos descartables: los aporta el paciente o el prestador con cargo. Las prestaciones adicionales tienen descuentos progresivos del 20%, 30% y 40% según el servicio.', '3 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (4, 'Laboratorio', 'PLUS', 'Reintegro de hasta 30% de los gastos en laboratorios adheridos (Laboratorio del Este).', '6 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (4, 'Laboratorio', 'INT', 'Reintegro de hasta 30% de los gastos en laboratorios adheridos (Laboratorio del Este).', '6 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (5, 'Medicina hiperbárica', 'PLUS', 'Cinco (5) sesiones sin costo por plan, individual o grupal. Las sesiones adicionales tienen 50% de descuento.', 'Sesiones sin costo: 6 meses · Descuento: 7 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (5, 'Medicina hiperbárica', 'INT', 'Una (1) sesión anual sin costo por plan, individual o grupal. Las sesiones adicionales tienen 30% de descuento.', 'Sesión sin costo: 6 meses · Descuento: 3 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (6, 'Óptica', 'PLUS', 'A partir de los tres (3) meses, hasta 50% en productos seleccionados.', 'Sin carencias');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (6, 'Óptica', 'INT', 'Descuentos escalonados en ópticas adheridas: 10% al inicio, 20% a los tres (3) meses, 30% a los seis (6) meses y hasta 50% en productos seleccionados desde el primer año de afiliación.', 'Sin carencias');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (7, 'Seguro de sepelio', 'PLUS', 'Servicio completo: cajón, velatorio, traslado hasta veinte (20) km sin costo, cremación y/o entierro. Tiene costo diferenciado según la edad del afiliado.', '6 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (7, 'Seguro de sepelio', 'INT', 'Servicio completo: cajón, velatorio, traslado hasta veinte (20) km sin costo, cremación y/o entierro. Tiene costo diferenciado según la edad del afiliado.', '6 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (7, 'Seguro de sepelio', 'SEPELIO', 'Servicio completo: cajón, velatorio, traslado hasta veinte (20) km sin costo, cremación y/o entierro. Tiene costo diferenciado según la edad del afiliado.', '6 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (8, 'Odontología', 'PLUS', 'Una (1) consulta cada seis (6) meses sin costo (no incluye radiografía). Hasta 50% de descuento en limpieza. Hasta 50% en tartrectomía, pulido dental (maxilar superior e inferior), restauraciones con composite (una, dos y tres caras) y extracciones generales.', 'Consulta: sin carencias · Limpieza: 3 meses · Resto: 6 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (8, 'Odontología', 'INT', 'Una (1) consulta cada seis (6) meses sin costo (no incluye radiografía). Hasta 50% de descuento en limpieza. Hasta 50% en tartrectomía, pulido dental (maxilar superior e inferior), restauraciones con composite (una, dos y tres caras) y extracciones generales.', 'Consulta: sin carencias · Limpieza: 3 meses · Resto: 6 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (9, 'Farmacia', 'PLUS', 'Descuentos en farmacias adheridas, en efectivo, de hasta: 20% en medicamentos estuchados, 30% en preparados magistrales, 10% en accesorios dentales, 10% en productos ortopédicos y 15% en pañales para adultos y pediátricos.', 'Sin carencias');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (9, 'Farmacia', 'INT', 'Descuentos en farmacias adheridas, en efectivo, de hasta: 20% en medicamentos estuchados, 30% en preparados magistrales, 10% en accesorios dentales, 10% en productos ortopédicos y 15% en pañales para adultos y pediátricos.', 'Sin carencias');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (10, 'Electrodomésticos y productos diversos', 'PLUS', 'Descuentos especiales y opciones de financiación flexibles en productos seleccionados.', '1 año');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (10, 'Electrodomésticos y productos diversos', 'INT', 'Descuentos especiales y opciones de financiación flexibles en productos seleccionados.', '1 año');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (11, 'Aporte solidario para cirugías no estéticas', 'PLUS', 'Hasta $30.000 en cirugías no estéticas. Utilizable una vez por año calendario.', '6 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (12, 'Indemnización por internación sin cirugía', 'PLUS', 'Hasta $30.000 en gastos de internación sin cirugía. Utilizable una vez por año calendario.', '3 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (13, 'Estudios médicos', 'PLUS', 'Hasta 20% de descuento en tratamientos y estudios no cubiertos por obra social o prepaga, solo en instituciones con convenio.', '3 meses');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (14, 'Gimnasio', 'PLUS', 'San Benito Energy Fitness: hasta 15%. Fit Form Colonia: hasta 10% o 15%.', 'Sin carencias');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (15, 'Leguan', 'PLUS', 'Hasta 10% de descuento en todas las actividades.', 'Sin carencias');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (16, 'Tiendas de indumentaria', 'PLUS', 'Marroquinería Pronoia: hasta 20% en efectivo o transferencia, con envío sin cargo a puntos de retiro.', 'Sin carencias');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (17, 'Viajes turísticos y hotelería', 'PLUS', 'Descuentos en viajes y hotelería.', 'Sin carencias');
INSERT INTO beneficios_plan (orden, rubro, plan_id, texto, carencia) VALUES (18, 'Barbería Mauro Leiva', 'PLUS', 'Descuento del 20%.', 'Sin carencias');

-- Folleto (textos resumidos por plan)
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('INT', 1, 'Consultas médicas', 'Consulta anual con beneficios en especialidades básicas y hasta 30% de descuento en otras atenciones médicas.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('INT', 2, 'Enfermería', 'Una atención anual sin cargo en consultorio o a domicilio. Luego, descuentos progresivos en servicios de enfermería.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('INT', 3, 'Laboratorio', 'Reintegros de hasta 30% en estudios realizados en laboratorios adheridos.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('INT', 4, 'Medicina hiperbárica', 'Una sesión sin costo y 30% de descuento en sesiones posteriores.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('INT', 5, 'Óptica', 'Descuentos escalonados en ópticas adheridas, alcanzando hasta un 50% de ahorro.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('INT', 6, 'Cobertura de sepelio', 'Acompañamiento integral que incluye velatorio, traslado, cremación o inhumación, sin costos adicionales (hasta 20 km).');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('INT', 7, 'Odontología', 'Beneficios y descuentos en prácticas odontológicas en centros adheridos. Consultas y tratamientos con beneficios diferenciales.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('INT', 8, 'Farmacia', 'Descuentos en medicamentos, preparados magistrales, ortopedia y productos de cuidado personal.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('INT', 9, 'Electrodomésticos y productos diversos', 'Descuentos exclusivos y opciones de financiación en productos seleccionados.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('PLUS', 1, 'Aporte solidario', 'Cobertura económica para cirugías no estéticas e internaciones, con apoyo ante gastos médicos imprevistos.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('PLUS', 2, 'Medicina hiperbárica', 'Cinco sesiones sin costo y 50% de descuento en sesiones adicionales.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('PLUS', 3, 'Óptica', 'Accedé a un 50% de descuento en ópticas adheridas a partir de los 3 meses.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('PLUS', 4, 'Estudios médicos', 'Descuentos en estudios y tratamientos sin cobertura de obra social o prepaga, en centros convenidos.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('PLUS', 5, 'Electrodomésticos y productos diversos', 'Descuentos especiales y financiación al costo en productos seleccionados.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('PLUS', 6, 'Descuentos en comercios adheridos', 'Beneficios exclusivos en una amplia red de comercios, sin períodos de espera.');
INSERT INTO folleto (plan_id, orden, titulo, texto) VALUES ('PLUS', 7, 'Viajes turísticos y hotelería', 'Beneficios en viajes y hotelería, con propuestas pensadas para el descanso, la salud y el bienestar.');

-- Condiciones generales
INSERT INTO condiciones (orden, texto) VALUES (1, 'Integrantes adicionales del grupo (Plan Integral y Plan Plus): 25% de descuento los menores de 18 años, 20% de 18 a 49 años, 10% de 50 a 64 años; los mayores de 64 no tienen descuento. A partir de los 64 años la cuota tiene $4.000 adicionales.');
INSERT INTO condiciones (orden, texto) VALUES (2, 'Plan Salud y Plan Sepelio son individuales: no se pueden sumar integrantes.');
INSERT INTO condiciones (orden, texto) VALUES (3, 'Seguro de sepelio: edad máxima de adhesión 74 años inclusive. Excepcionalmente hasta 76 años con certificado médico de buena salud, sujeto a evaluación. Quien ya está afiliado y supera esas edades mantiene el beneficio.');

-- Plan de cuentas (CU-34) y categorías de egresos (CU-33)
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('1', 'ACTIVO', false, 1);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('1.1', 'Caja', true, 2);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('1.2', 'Banco y billeteras', true, 3);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('1.3', 'Cuotas a cobrar', true, 4);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('1.4', 'IVA crédito fiscal', true, 5);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('2', 'PASIVO', false, 6);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('2.1', 'Proveedores a pagar', true, 7);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('3', 'PATRIMONIO NETO', false, 8);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('3.1', 'Capital', true, 9);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('4', 'INGRESOS', false, 10);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('4.1', 'Cuotas de afiliados', true, 11);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5', 'EGRESOS', false, 12);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.1', 'Sistema y web', true, 13);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.2', 'Seguro de sepelio', true, 14);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.3', 'Otros gastos de administración', true, 15);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.4', 'Alquiler', true, 16);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.5', 'Sueldos y cargas sociales', true, 17);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.6', 'Honorarios profesionales', true, 18);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.7', 'Insumos y librería', true, 19);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.8', 'Servicios (luz, internet, teléfono)', true, 20);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.9', 'Ayudas económicas a afiliados', true, 21);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.10', 'Publicidad', true, 22);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.11', 'Comisiones bancarias y de cobro', true, 23);
INSERT INTO cuentas (codigo, nombre, imputable, orden) VALUES ('5.12', 'Impuestos y tasas', true, 24);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('alquiler', 'Alquiler', '5.4', 1);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('sueldos', 'Sueldos y cargas sociales', '5.5', 2);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('honorarios', 'Honorarios profesionales', '5.6', 3);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('insumos', 'Insumos y librería', '5.7', 4);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('servicios', 'Servicios (luz, internet, teléfono)', '5.8', 5);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('sistema', 'Sistema y web', '5.1', 6);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('sepelio', 'Seguro de sepelio', '5.2', 7);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('ayudas', 'Ayudas económicas a afiliados', '5.9', 8);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('publicidad', 'Publicidad', '5.10', 9);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('comisiones', 'Comisiones bancarias y de cobro', '5.11', 10);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('impuestos', 'Impuestos y tasas', '5.12', 11);
INSERT INTO categorias_egreso (clave, nombre, cuenta, orden) VALUES ('admin', 'Otros gastos de administración', '5.3', 12);

-- Configuración general
INSERT INTO config (clave, valor, actualizado_por) VALUES ('empresa', '{"razon":"Grupo del Este Salud S.A.S.","cuit":"30-71644749-5","iva":"IVA Responsable Inscripto","iibb":"30-71644749-5 (ATER, contribuyente directo)","inicio":"05/2019","domicilio":"Gral. José Gervasio Artigas 371","localidad":"Colonia Avellaneda (3107), Entre Ríos"}'::jsonb, 'sistema');
INSERT INTO config (clave, valor, actualizado_por) VALUES ('inicio', '{"fecha":"2026-10-31","periodo":"2026-11"}'::jsonb, 'sistema');
INSERT INTO config (clave, valor, actualizado_por) VALUES ('dia_vto', '10'::jsonb, 'sistema');
INSERT INTO config (clave, valor, actualizado_por) VALUES ('carencias', '{"consulta":0,"estudio":0,"beneficio":0}'::jsonb, 'sistema');
INSERT INTO config (clave, valor, actualizado_por) VALUES ('punto_venta', '{"cuotas":1}'::jsonb, 'sistema');
INSERT INTO config (clave, valor, actualizado_por) VALUES ('ultimos_numeros', '{"B":0,"A":0}'::jsonb, 'sistema');
INSERT INTO config (clave, valor, actualizado_por) VALUES ('permisos', '{"editar":true,"descuentos":true,"montos":true,"padron":true}'::jsonb, 'sistema');
INSERT INTO config (clave, valor, actualizado_por) VALUES ('mail', '{"factura":{"asunto":"Integral del Este · Factura de tu cuota de {periodo}","cuerpo":"Hola {nombre}:\n\nTe enviamos adjunta la factura de tu cuota de {periodo} del {plan}, por {importe}, con vencimiento el {vencimiento}.\n\nPodés abonarla en nuestra oficina o por transferencia. Si ya la pagaste, no tengas en cuenta este mensaje.\n\nSaludos cordiales,\nIntegral del Este\nArtigas 371, Colonia Avellaneda, Entre Ríos\nWhatsApp: 3435446701 · integraldeleste.com.ar"},"recibo":{"asunto":"Integral del Este · Recibo de tu pago","cuerpo":"Hola {nombre}:\n\nTe enviamos adjunto el recibo de tu pago de {importe} correspondiente a la cuota de {periodo}.\n\n¡Gracias por ser parte de Integral del Este!\n\nIntegral del Este\nArtigas 371, Colonia Avellaneda, Entre Ríos\nWhatsApp: 3435446701 · integraldeleste.com.ar"},"resumen":{"asunto":"Integral del Este · Tus beneficios de {periodo}","cuerpo":"Hola {nombre}:\n\nTe contamos los beneficios que usaste en {periodo} por ser parte de Integral del Este:\n\n{detalle}\n\nAhorro total del mes: {importe}\n\n¡Gracias por elegirnos!\n\nIntegral del Este\nArtigas 371, Colonia Avellaneda, Entre Ríos\nWhatsApp: 3435446701 · integraldeleste.com.ar"},"promo":{"asunto":"Integral del Este · {titulo}","cuerpo":"Hola {nombre}:\n\nTenemos una promoción especial para vos por ser afiliado de Integral del Este:\n\n{beneficio} en {lugar}.\n\n{texto}\n\nVálida del {desde} al {hasta}. Te adjuntamos el PDF con todos los detalles.\n\n¡Aprovechala!\n\nIntegral del Este\nArtigas 371, Colonia Avellaneda, Entre Ríos\nWhatsApp: 3435446701 · integraldeleste.com.ar"},"recibo_auto":false}'::jsonb, 'sistema');
INSERT INTO config (clave, valor, actualizado_por) VALUES ('precios_actualizados', '{"fecha":null,"usuario":null}'::jsonb, 'sistema');

-- Numeradores por punto de venta (las facturas siguen el último número configurado)
INSERT INTO numeradores (clave, ultimo) VALUES ('FB-1', 0), ('FA-1', 0), ('R-1', 0);

-- Promoción de ejemplo de la maqueta (v81), vigente este mes y el próximo
INSERT INTO promociones (titulo, beneficio, unidad, desde, hasta, texto, cond, planes, por) VALUES ('Llegó el verano', '40% OFF en anteojos de sol', 'Óptica', date_trunc('month', current_date)::date, (date_trunc('month', current_date) + interval '2 month - 1 day')::date, 'Para arrancar el verano, los afiliados de Integral del Este tienen 40% de descuento en todos los anteojos de sol de la Óptica. Además, el control de la vista es sin cargo.', 'No acumulable con otras promociones. Hasta agotar stock', '{}', 'Santiago');
