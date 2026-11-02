-- =====================================================================
-- Integral del Este — 011 · Usuarios y padrón inicial
-- =====================================================================
-- Punto de partida (v7): 24 afiliados del Excel Control_Cobranzas,
-- todos al día al 31/10/2026. Se factura desde noviembre 2026.
-- Claves iniciales: iguales al nombre de usuario (Argon2id). El sistema
-- obliga a cambiarlas en el primer ingreso (S-06 de Arquitectura).
-- =====================================================================

-- Administradores (dueños y Katy)
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones) VALUES ('Santiago', 'santiago', '$argon2id$v=19$m=65536,t=4,p=1$RGtnVVZKQTN4cWw2L20yUQ$vWjlp7kC7Po4wTaLiGY2kchrh5WPgYyCFzeemAxe0nE', 'admin', '{"afiliados","altas","cotizador","autorizaciones","auditoria","prestaciones","beneficios","retencion","caja","egresos","informes","cuentas","contabilidad","estadisticas","promociones","config"}'::text[]);
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones) VALUES ('Mauro', 'mauro', '$argon2id$v=19$m=65536,t=4,p=1$OVdGelk0WVpMdTZWM3o1Nw$C0XkGMn+fZ3XwMjEFfHgLg9XkmoRU3Qn73f7hY8jKi0', 'admin', '{"afiliados","altas","cotizador","autorizaciones","auditoria","prestaciones","beneficios","retencion","caja","egresos","informes","cuentas","contabilidad","estadisticas","promociones","config"}'::text[]);
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones) VALUES ('Pablo', 'pablo', '$argon2id$v=19$m=65536,t=4,p=1$cDlGNng0QTJMQjBpRnhDQw$ZcT9hK+3e2zN59nHH0Tg726+HhkKTciJgpifHXyXfJA', 'admin', '{"afiliados","altas","cotizador","autorizaciones","auditoria","prestaciones","beneficios","retencion","caja","egresos","informes","cuentas","contabilidad","estadisticas","promociones","config"}'::text[]);
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones) VALUES ('Katy', 'katy', '$argon2id$v=19$m=65536,t=4,p=1$L3VGVjc5SmtQTjVzZUNDTg$uM5nvRMH76XAJkrO/vqh6zyj6lnI6rnhH4KV1OkJh+Q', 'admin', '{"afiliados","altas","cotizador","autorizaciones","auditoria","prestaciones","beneficios","retencion","caja","egresos","informes","cuentas","contabilidad","estadisticas","promociones","config"}'::text[]);
-- Usuarios de atención
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones) VALUES ('Belén', 'belen', '$argon2id$v=19$m=65536,t=4,p=1$S0dLM25GVkxkaGEwVDFFLg$vDtgOUVvkQk6jcpJB29V3Pzm3CUkqjnB9WYPS0K2gG8', 'usuario', '{"altas","cotizador","autorizaciones","beneficios","retencion"}'::text[]);
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones) VALUES ('Cecilia', 'cecilia', '$argon2id$v=19$m=65536,t=4,p=1$V0QuSTR0bEJXeXJWZ1UxNw$EwtwTGlaJjwKJdE89mbCHDb4Cl8iiT/j6yMu6vjmcyo', 'usuario', '{"altas","cotizador","autorizaciones","beneficios","retencion"}'::text[]);
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones) VALUES ('Angie', 'angie', '$argon2id$v=19$m=65536,t=4,p=1$OUpQQU9FQmh2Zktld2RZeg$PtSf/NBz8t4o/9Y/g5p9u56SVAsZTr1C6aKzBWMN/es', 'usuario', '{"altas","cotizador","autorizaciones","beneficios","retencion"}'::text[]);
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones) VALUES ('Sofía', 'sofia', '$argon2id$v=19$m=65536,t=4,p=1$MVN2V0pvdUVQTEx4dWtBYQ$Tbe5OGJlllw3IKrmEGILHlRZ5OAmBGbCOc0IdcJqG7k', 'usuario', '{"altas","cotizador","autorizaciones","beneficios","retencion"}'::text[]);
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones) VALUES ('Mari', 'mari', '$argon2id$v=19$m=65536,t=4,p=1$MVFVSVdoMEd1U0M1bzAzcQ$dzYvwpKLS0ku6Coa4kh9XufJiy8va7JR0AQx8bgKs14', 'usuario', '{"altas","cotizador","autorizaciones","beneficios","retencion"}'::text[]);
-- CMDE dejó de ser unidad en la v78: emite autorizaciones para las demás unidades
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones) VALUES ('CMDE', 'cmde', '$argon2id$v=19$m=65536,t=4,p=1$T1oxTllkL3hDdmZmaUdaeg$3QSEBLywSKwXafKdJfJp3kvyYaUyZdy+AfGc9VCdSyc', 'usuario', '{"altas","cotizador","autorizaciones","beneficios","retencion"}'::text[]);
-- Unidades de negocio
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones, unidad_id) VALUES ('Biobárica', 'biobarica', '$argon2id$v=19$m=65536,t=4,p=1$bXRpOVdxcGtiekhacEpYYg$qgteX36GbH+xcJ229nfpAPiL/Van4kJwJb8Fr7QnZoo', 'unidad', '{"verificar"}'::text[], (SELECT id FROM unidades WHERE nombre = 'Biobárica'));
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones, unidad_id) VALUES ('Laboratorio', 'laboratorio', '$argon2id$v=19$m=65536,t=4,p=1$MHNtLmpHOEJOMmF2TGhXcQ$TeUpTs8wdBYONV6VU7xYVHY0gVFTbY1ttu9RLjX89sk', 'unidad', '{"verificar"}'::text[], (SELECT id FROM unidades WHERE nombre = 'Laboratorio'));
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones, unidad_id) VALUES ('Óptica', 'optica', '$argon2id$v=19$m=65536,t=4,p=1$b0N2LlI2Y2gxRlpsRXJMaw$wq7qmlCjiXKRFXORmHpBSFjYHCpv4o43xgTPXFv44dc', 'unidad', '{"verificar"}'::text[], (SELECT id FROM unidades WHERE nombre = 'Óptica'));
-- Comercios adheridos
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones, comercio_nombre, comercio_rubro) VALUES ('sanbenito', 'sanbenito', '$argon2id$v=19$m=65536,t=4,p=1$V1pjcDUvdmUzS05vWWpBbQ$CitCG8BATsvTuftJA3wt7gCIMvL9lvrlPUsVoUAhukw', 'comercio', '{"verificar"}'::text[], 'San Benito Energy Fitness', 'Gimnasio');
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones, comercio_nombre, comercio_rubro) VALUES ('fitform', 'fitform', '$argon2id$v=19$m=65536,t=4,p=1$OW1CeVBLZzhLWUZFRm9LMg$LEvvG9SrB1x1qzl0WjQS3bQ7wFkb9SUWh42L5Z9Yqsg', 'comercio', '{"verificar"}'::text[], 'Fit Form Colonia', 'Gimnasio');
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones, comercio_nombre, comercio_rubro) VALUES ('leguan', 'leguan', '$argon2id$v=19$m=65536,t=4,p=1$cloyQ2FsWXYuLmg2NkxwTg$rIjGx1Puw0RG1upH8qB3+Ipc9fDmyS9Y35nWWpwd7N0', 'comercio', '{"verificar"}'::text[], 'Leguan', 'Leguan');
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones, comercio_nombre, comercio_rubro) VALUES ('pronoia', 'pronoia', '$argon2id$v=19$m=65536,t=4,p=1$OTcyM2dYemZFUlR6US5ZZw$SbG4bV4nwqriIEQPNGlRs3zPlPSiPkzrpK9teNrZRQo', 'comercio', '{"verificar"}'::text[], 'Marroquinería Pronoia', 'Tiendas de indumentaria');
INSERT INTO usuarios (nombre, nombre_norm, clave_hash, rol, secciones, comercio_nombre, comercio_rubro) VALUES ('barberia', 'barberia', '$argon2id$v=19$m=65536,t=4,p=1$QnV0Lm0yZUROc1lWdDlmYQ$ufdOm0faaDpRppxt6HbBSyejEjcFgkbxVu/cETD3xRM', 'comercio', '{"verificar"}'::text[], 'Barbería Mauro Leiva', 'Barbería Mauro Leiva');

-- Grupos de pago y afiliados
WITH g AS (INSERT INTO grupos (plan_id, forma_pago, fecha_alta) VALUES ('PLUS', 'Efectivo', '2026-06-01') RETURNING id)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre2, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, telefono, obs)
SELECT v.* FROM g, LATERAL (VALUES
  (62, g.id, 0, 'VILLAGRA', 'SOFIA', 'CANDELA', 'VILLAGRA SOFIA CANDELA', 'villagra sofia candela', '43.775.786', '43775786', 'TIT', 25000, '2026-06-01'::date, 'activo', '', '')
) AS v;
WITH g AS (INSERT INTO grupos (plan_id, forma_pago, fecha_alta) VALUES ('PLUS', 'Débito automático', '2026-05-01') RETURNING id)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre2, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, telefono, obs)
SELECT v.* FROM g, LATERAL (VALUES
  (65, g.id, 0, 'ALTERINI', 'HUGO', '', 'ALTERINI HUGO', 'alterini hugo', '11.379.975', '11379975', 'TIT64', 29000, '2026-05-01'::date, 'activo', '', '')
) AS v;
WITH g AS (INSERT INTO grupos (plan_id, forma_pago, fecha_alta) VALUES ('PLUS', 'Débito automático', '2026-05-01') RETURNING id)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre2, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, telefono, obs)
SELECT v.* FROM g, LATERAL (VALUES
  (68, g.id, 0, 'VILAR', 'IVAN', '', 'VILAR IVAN', 'vilar ivan', '33.458.640', '33458640', 'TIT', 25000, '2026-05-01'::date, 'activo', '', 'Son pareja, abonan $45.000'),
  (69, g.id, 1, 'GODOY', 'CIELO', '', 'GODOY CIELO', 'godoy cielo', '37.470.330', '37470330', 'CON', 20000, '2026-05-01'::date, 'activo', '', '')
) AS v;
WITH g AS (INSERT INTO grupos (plan_id, forma_pago, fecha_alta) VALUES ('PLUS', 'Débito automático', '2026-05-01') RETURNING id)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre2, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, telefono, obs)
SELECT v.* FROM g, LATERAL (VALUES
  (70, g.id, 0, 'GONZALEZ', 'MAURO', 'GABRIEL', 'GONZALEZ MAURO GABRIEL', 'gonzalez mauro gabriel', '32.105.117', '32105117', 'TIT', 25000, '2026-05-01'::date, 'activo', '', 'Son familia, abonan $82.500'),
  (71, g.id, 1, 'AGUILAR', 'MARISA', 'VIVIANA', 'AGUILAR MARISA VIVIANA', 'aguilar marisa viviana', '26.650.977', '26650977', 'CON', 20000, '2026-05-01'::date, 'activo', '', ''),
  (72, g.id, 2, 'GONZALEZ', 'ANGELA', '', 'GONZALEZ ANGELA', 'gonzalez angela', '56.363.208', '56363208', 'HIJ', 18750, '2026-05-01'::date, 'activo', '', ''),
  (73, g.id, 3, 'GONZALEZ', 'MAURO', 'SALVADOR', 'GONZALEZ MAURO SALVADOR', 'gonzalez mauro salvador', '58.079.272', '58079272', 'HIJ', 18750, '2026-05-01'::date, 'activo', '', ''),
  (83, g.id, 4, 'GONZALEZ', 'RAUL', 'IGNACIO', 'GONZALEZ RAUL IGNACIO', 'gonzalez raul ignacio', '14.165.118', '14165118', 'TIT64', 29000, '2026-06-01'::date, 'activo', '', 'Papas de Dr Gonzalez Mauro, abona èl'),
  (84, g.id, 5, 'SIONE', 'BLANCA', 'NORMA', 'SIONE BLANCA NORMA', 'sione blanca norma', '12.210.548', '12210548', 'CON64', 29000, '2026-06-01'::date, 'activo', '', '')
) AS v;
WITH g AS (INSERT INTO grupos (plan_id, forma_pago, fecha_alta) VALUES ('PLUS', 'Transferencia bancaria', '2026-05-01') RETURNING id)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre2, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, telefono, obs)
SELECT v.* FROM g, LATERAL (VALUES
  (74, g.id, 0, 'GOLTZ', 'DANTE', '', 'GOLTZ DANTE', 'goltz dante', '16.612.302', '16612302', 'TIT', 25000, '2026-05-01'::date, 'activo', '', '')
) AS v;
WITH g AS (INSERT INTO grupos (plan_id, forma_pago, fecha_alta) VALUES ('PLUS', 'Efectivo', '2026-05-01') RETURNING id)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre2, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, telefono, obs)
SELECT v.* FROM g, LATERAL (VALUES
  (75, g.id, 0, 'RAMALLO', 'JULIETA', 'ILEANA', 'RAMALLO JULIETA ILEANA', 'ramallo julieta ileana', '28.913.458', '28913458', 'TIT', 25000, '2026-05-01'::date, 'activo', '', 'Son familia, abonan $101.250 + Vismara'),
  (76, g.id, 1, 'ALTERINI', 'PABLO', 'ANIBAL', 'ALTERINI PABLO ANIBAL', 'alterini pablo anibal', '27.618.173', '27618173', 'CON', 20000, '2026-05-01'::date, 'activo', '', ''),
  (77, g.id, 2, 'ALTERINI', 'BENJAMIN', '', 'ALTERINI BENJAMIN', 'alterini benjamin', '48.724.462', '48724462', 'HIJ', 18750, '2026-05-01'::date, 'activo', '', ''),
  (78, g.id, 3, 'ALTERINI', 'PEDRO', '', 'ALTERINI PEDRO', 'alterini pedro', '51.285.971', '51285971', 'HIJ', 18750, '2026-05-01'::date, 'activo', '', ''),
  (79, g.id, 4, 'ALTERINI', 'ALFONSINA', '', 'ALTERINI ALFONSINA', 'alterini alfonsina', '57.561.373', '57561373', 'HIJ', 18750, '2026-05-01'::date, 'activo', '', ''),
  (64, g.id, 5, 'VISMARA', 'CLAUDIA', '', 'VISMARA CLAUDIA', 'vismara claudia', '13.883.359', '13883359', 'CON64', 29000, '2026-05-01'::date, 'activo', '', 'Abona dr Pablo')
) AS v;
WITH g AS (INSERT INTO grupos (plan_id, forma_pago, fecha_alta) VALUES ('PLUS', 'Transferencia bancaria', '2026-05-01') RETURNING id)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre2, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, telefono, obs)
SELECT v.* FROM g, LATERAL (VALUES
  (80, g.id, 0, 'SANTA CRUZ', 'GRACIELA', '', 'SANTA CRUZ GRACIELA', 'santa cruz graciela', '27.813.583', '27813583', 'TIT', 25000, '2026-05-01'::date, 'activo', '', '')
) AS v;
WITH g AS (INSERT INTO grupos (plan_id, forma_pago, fecha_alta) VALUES ('PLUS', 'Débito automático', '2026-05-01') RETURNING id)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre2, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, telefono, obs)
SELECT v.* FROM g, LATERAL (VALUES
  (82, g.id, 0, 'ALTERINI', 'ALICIA', '', 'ALTERINI ALICIA', 'alterini alicia', '6.501.683', '6501683', 'TIT64', 29000, '2026-05-01'::date, 'activo', '', '')
) AS v;
WITH g AS (INSERT INTO grupos (plan_id, forma_pago, fecha_alta) VALUES ('PLUS', 'Transferencia bancaria', '2026-08-01') RETURNING id)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre2, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, telefono, obs)
SELECT v.* FROM g, LATERAL (VALUES
  (89, g.id, 0, 'DESIREE', 'BOLSA', 'LEONELA', 'DESIREE BOLSA LEONELA', 'desiree bolsa leonela', '20-34014173-4', '34014173', 'TIT', 25000, '2026-08-01'::date, 'activo', '', ''),
  (90, g.id, 1, 'GARRIDO', 'FERNADO', 'MARTIN', 'GARRIDO FERNADO MARTIN', 'garrido fernado martin', '27-34453814-5', '34453814', 'CON', 20000, '2026-08-01'::date, 'activo', '', '')
) AS v;
WITH g AS (INSERT INTO grupos (plan_id, forma_pago, fecha_alta) VALUES ('PLUS', 'Mercado Pago', '2026-09-01') RETURNING id)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre2, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, telefono, obs)
SELECT v.* FROM g, LATERAL (VALUES
  (91, g.id, 0, 'BRUN', 'MABEL', '', 'BRUN MABEL', 'brun mabel', '23-13534788-4', '13534788', 'TIT', 25000, '2026-09-01'::date, 'activo', '3435013651', 'Se suscribio a MP - $23.750')
) AS v;
WITH g AS (INSERT INTO grupos (plan_id, forma_pago, fecha_alta) VALUES ('PLUS', 'Efectivo', '2026-10-01') RETURNING id)
INSERT INTO afiliados (nro, grupo_id, orden, apellido, nombre1, nombre2, nombre, nombre_norm, doc, doc_norm, categoria_id, cuota, fecha_alta, estado, telefono, obs)
SELECT v.* FROM g, LATERAL (VALUES
  (92, g.id, 0, 'MIÑO', 'NATALIA', 'ANDREA', 'MIÑO NATALIA ANDREA', 'mino natalia andrea', '27-25616274-7', '25616274', 'TIT', 25000, '2026-10-01'::date, 'activo', '3434628168', 'Madre e hija - Hija abona con dto: Total $45.000'),
  (93, g.id, 1, 'MISERE', 'ALEJANDRINA', 'ANDREA', 'MISERE ALEJANDRINA ANDREA', 'misere alejandrina andrea', '22.160.002', '22160002', 'CON', 20000, '2026-10-01'::date, 'activo', '3434628168', '')
) AS v;

SELECT setval('afiliado_nro_seq', (SELECT max(nro) FROM afiliados));

INSERT INTO historial (accion, detalle, usuario) VALUES ('Inicio', 'Punto de partida: 24 afiliados del Excel, todos al día al 31/10/2026. Se factura desde noviembre 2026.', 'sistema');
