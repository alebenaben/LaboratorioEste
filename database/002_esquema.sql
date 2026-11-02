-- =====================================================================
-- Integral del Este — 002 · Esquema
-- =====================================================================
-- Tablas del sistema. Los nombres siguen los Casos de uso (CU-xx) y las
-- reglas transversales (RT-xx). Se ejecuta como el dueño ("integral").
-- =====================================================================

SET client_min_messages = warning;

-- Toda tabla o secuencia nueva que cree el dueño queda accesible para la app.
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO integral_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO integral_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO integral_backup;

-- ---------------------------------------------------------------------
-- Control de migraciones
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migraciones (
  archivo     text PRIMARY KEY,
  aplicada_en timestamptz NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- Unidades de negocio (CU-43) y planes / categorías / precios (CU-45)
-- ---------------------------------------------------------------------
CREATE TABLE unidades (
  id               serial PRIMARY KEY,
  nombre           text NOT NULL UNIQUE,          -- clave corta: CMDE, Óptica, Biobárica, Laboratorio…
  nombre_largo     text NOT NULL,
  servicio         text NOT NULL DEFAULT '',
  carencia_meses   int  NOT NULL DEFAULT 0 CHECK (carencia_meses BETWEEN 0 AND 24),
  texto_beneficio  text NOT NULL DEFAULT '',
  punto_venta      int  NOT NULL DEFAULT 1,
  original         boolean NOT NULL DEFAULT false, -- las originales no se renombran ni eliminan
  activo           boolean NOT NULL DEFAULT true,
  creado           timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE planes (
  id          text PRIMARY KEY,                   -- PLUS, INT, SALUD, SEPELIO
  nombre      text NOT NULL,
  individual  boolean NOT NULL DEFAULT false,
  alicuota    numeric(5,2),                       -- NULL = "a confirmar"; 0 = exento
  orden       int NOT NULL DEFAULT 0,
  inferior    text REFERENCES planes(id)          -- plan al que se baja en retención (CU-12)
);

CREATE TABLE categorias (
  id       text PRIMARY KEY,                      -- TIT, TIT64, HIJ, CON, A50, CON64
  nombre   text NOT NULL,
  titular  boolean NOT NULL DEFAULT false,
  orden    int NOT NULL DEFAULT 0
);

CREATE TABLE precios (
  plan_id      text NOT NULL REFERENCES planes(id),
  categoria_id text NOT NULL REFERENCES categorias(id),
  precio       numeric(12,2) NOT NULL DEFAULT 0 CHECK (precio >= 0),  -- 0 = no se vende
  PRIMARY KEY (plan_id, categoria_id)
);

-- ---------------------------------------------------------------------
-- Prestaciones, reglas de beneficio y catálogo de beneficios
-- ---------------------------------------------------------------------
CREATE TABLE prestaciones (
  cod                 text PRIMARY KEY,
  nombre              text NOT NULL,
  tipo                text NOT NULL CHECK (tipo IN ('consulta','estudio','beneficio')),
  unidad_id           int  NOT NULL REFERENCES unidades(id),
  valor_ref           numeric(12,2) NOT NULL DEFAULT 0,
  requiere_auditoria  boolean NOT NULL DEFAULT false,
  iva                 numeric(5,2),
  activo              boolean NOT NULL DEFAULT true,
  orden               int NOT NULL DEFAULT 0,
  actualizado_en      timestamptz,
  actualizado_por     text
);
CREATE UNIQUE INDEX prestaciones_nombre_unidad ON prestaciones (unidad_id, lower(nombre));

-- RT-06: una regla por plan y prestación
CREATE TABLE reglas_beneficio (
  plan_id        text NOT NULL REFERENCES planes(id),
  prestacion_cod text NOT NULL REFERENCES prestaciones(cod),
  gratis         int  NOT NULL DEFAULT 0 CHECK (gratis >= 0),
  per            text NOT NULL DEFAULT 'anio' CHECK (per IN ('mes','semestre','anio')),
  por_grupo      boolean NOT NULL DEFAULT false,
  car_gratis     int  NOT NULL DEFAULT 0,        -- días
  descuento      numeric(5,2) NOT NULL DEFAULT 0,
  modo           text NOT NULL DEFAULT 'fijo' CHECK (modo IN ('fijo','hasta')),
  car            int  NOT NULL DEFAULT 0,        -- días de carencia del descuento
  escala         text NOT NULL DEFAULT '',       -- "0:10, 90:20, 180:30"
  max_desc       int  NOT NULL DEFAULT 0,        -- máximo con descuento por año (0 = sin tope)
  PRIMARY KEY (plan_id, prestacion_cod)
);

-- CU-44: beneficios en comercios adheridos por rubro
CREATE TABLE rubros_comercio (
  nombre   text PRIMARY KEY,
  max_pct  numeric(5,2) NOT NULL DEFAULT 0,
  limite   int NOT NULL DEFAULT 0,               -- 0 = sin límite
  per      text NOT NULL DEFAULT 'anio' CHECK (per IN ('mes','semestre','anio')),
  orden    int NOT NULL DEFAULT 0
);

-- CU-16: catálogo "Beneficios detallados por planes principales"
CREATE TABLE beneficios_plan (
  id        serial PRIMARY KEY,
  orden     int NOT NULL,
  rubro     text NOT NULL,
  plan_id   text NOT NULL REFERENCES planes(id),
  texto     text NOT NULL,
  carencia  text NOT NULL DEFAULT 'Sin carencias',
  UNIQUE (rubro, plan_id)
);

CREATE TABLE folleto (                            -- textos resumidos del folleto por plan
  id       serial PRIMARY KEY,
  plan_id  text NOT NULL REFERENCES planes(id),
  orden    int NOT NULL,
  titulo   text NOT NULL,
  texto    text NOT NULL
);

CREATE TABLE condiciones (
  orden  int PRIMARY KEY,
  texto  text NOT NULL
);

-- Configuración general (CU-46, CU-47, CU-48): una fila por bloque.
CREATE TABLE config (
  clave           text PRIMARY KEY,
  valor           jsonb NOT NULL,
  actualizado     timestamptz NOT NULL DEFAULT now(),
  actualizado_por text
);

-- ---------------------------------------------------------------------
-- Usuarios, sesiones y seguridad (CU-01 a CU-04, CU-39)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id                  serial PRIMARY KEY,
  nombre              text NOT NULL,
  nombre_norm         text NOT NULL UNIQUE,     -- sin tildes ni mayúsculas
  clave_hash          text NOT NULL,            -- Argon2id
  rol                 text NOT NULL CHECK (rol IN ('admin','usuario','unidad','comercio')),
  activo              boolean NOT NULL DEFAULT true,
  secciones           text[] NOT NULL DEFAULT '{}',
  unidad_id           int REFERENCES unidades(id),
  comercio_nombre     text,
  comercio_rubro      text REFERENCES rubros_comercio(nombre) ON UPDATE CASCADE,
  debe_cambiar_clave  boolean NOT NULL DEFAULT true,
  creado              timestamptz NOT NULL DEFAULT now(),
  CHECK (rol <> 'unidad'   OR unidad_id IS NOT NULL),
  CHECK (rol <> 'comercio' OR comercio_nombre IS NOT NULL)
);

CREATE TABLE sesiones (
  id               text PRIMARY KEY,            -- sha256 del token de la cookie
  usuario_id       int NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  csrf             text NOT NULL,
  creada           timestamptz NOT NULL DEFAULT now(),
  ultima_actividad timestamptz NOT NULL DEFAULT now(),
  ip               text,
  user_agent       text,
  datos            jsonb NOT NULL DEFAULT '{}'   -- avisos de una sola vez y datos de la pantalla
);
CREATE INDEX sesiones_usuario ON sesiones (usuario_id);

CREATE TABLE intentos_ingreso (
  id           bigserial PRIMARY KEY,
  usuario_norm text NOT NULL,
  ip           text NOT NULL,
  ok           boolean NOT NULL,
  fecha        timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX intentos_usuario ON intentos_ingreso (usuario_norm, fecha);
CREATE INDEX intentos_ip ON intentos_ingreso (ip, fecha);

-- CU-04: autorización de un administrador para UNA acción
CREATE TABLE tokens_admin (
  token_hash  text PRIMARY KEY,
  accion      text NOT NULL,
  entidad     text NOT NULL DEFAULT '',
  admin_id    int NOT NULL REFERENCES usuarios(id),
  usuario_id  int NOT NULL REFERENCES usuarios(id),
  creado      timestamptz NOT NULL DEFAULT now(),
  usado       timestamptz
);

-- ---------------------------------------------------------------------
-- Afiliados y grupos (CU-05 a CU-14)
-- ---------------------------------------------------------------------
CREATE SEQUENCE afiliado_nro_seq START 1;

CREATE TABLE grupos (
  id               serial PRIMARY KEY,
  plan_id          text NOT NULL REFERENCES planes(id),
  forma_pago       text NOT NULL DEFAULT 'Efectivo',
  email            text NOT NULL DEFAULT '',
  sin_email        boolean NOT NULL DEFAULT false,
  fecha_alta       date NOT NULL,
  fiscal_cond      text NOT NULL DEFAULT 'CF' CHECK (fiscal_cond IN ('CF','RI','MT','EX')),
  fiscal_cuit      text NOT NULL DEFAULT '',
  fiscal_razon     text NOT NULL DEFAULT '',
  promo_pct        int CHECK (promo_pct IN (5,10,15,20,25,30,35,40)),
  promo_meses      int CHECK (promo_meses IN (1,2,3,6)),
  promo_desde      char(7),                     -- 'AAAA-MM'
  promo_hasta      char(7),
  promo_retencion  boolean NOT NULL DEFAULT false,
  creado           timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE afiliados (
  id            serial PRIMARY KEY,
  nro           int NOT NULL UNIQUE DEFAULT nextval('afiliado_nro_seq'),
  grupo_id      int NOT NULL REFERENCES grupos(id),
  orden         int NOT NULL DEFAULT 0,          -- 0 = titular (quien paga)
  apellido      text NOT NULL DEFAULT '',
  nombre1       text NOT NULL DEFAULT '',
  nombre2       text NOT NULL DEFAULT '',
  nombre        text NOT NULL,                   -- "APELLIDO NOMBRE1 NOMBRE2"
  nombre_norm   text NOT NULL,                   -- para buscar sin tildes
  doc           text NOT NULL DEFAULT '',        -- con formato: 28.913.458 o 20-34014173-4
  doc_norm      text NOT NULL DEFAULT '',        -- DNI interno (RT-01)
  nac           date,
  categoria_id  text NOT NULL REFERENCES categorias(id),
  cuota         numeric(12,2) NOT NULL DEFAULT 0,
  fecha_alta    date NOT NULL,
  estado        text NOT NULL DEFAULT 'activo' CHECK (estado IN ('activo','baja')),
  fecha_baja    date,
  telefono      text NOT NULL DEFAULT '',
  obs           text NOT NULL DEFAULT '',
  creado        timestamptz NOT NULL DEFAULT now()
);
ALTER SEQUENCE afiliado_nro_seq OWNED BY afiliados.nro;
CREATE INDEX afiliados_grupo ON afiliados (grupo_id, orden);
CREATE INDEX afiliados_doc ON afiliados (doc_norm);
CREATE INDEX afiliados_nombre ON afiliados (nombre_norm);

-- CU-14: saludos de cumpleaños (uno por afiliado y año)
CREATE TABLE saludos_cumple (
  afiliado_id int NOT NULL REFERENCES afiliados(id),
  anio        int NOT NULL,
  fecha       timestamptz NOT NULL DEFAULT now(),
  usuario     text NOT NULL DEFAULT '',
  PRIMARY KEY (afiliado_id, anio)
);

-- CU-10, CU-12, CU-13: casos de retención
CREATE TABLE retenciones (
  id          serial PRIMARY KEY,
  fecha       date NOT NULL DEFAULT current_date,
  creado      timestamptz NOT NULL DEFAULT now(),
  afiliado_id int NOT NULL REFERENCES afiliados(id),
  grupo_id    int NOT NULL REFERENCES grupos(id),
  motivo      text NOT NULL,
  comentario  text NOT NULL DEFAULT '',
  ofertas     jsonb NOT NULL DEFAULT '[]',      -- [{k,t,r:'acepta'|'no'}]
  resultado   text NOT NULL CHECK (resultado IN ('retenido','derivado','piensa','baja_pedida','baja')),
  aceptada    text,                             -- clave de la propuesta aceptada
  llamar      date,                             -- "lo va a pensar": fecha de llamado
  usuario     text NOT NULL DEFAULT '',
  cerrado     date                              -- se cierra con un registro posterior
);
CREATE INDEX retenciones_afiliado ON retenciones (afiliado_id, creado);

-- ---------------------------------------------------------------------
-- Caja: facturas y recibos (CU-26 a CU-32)
-- ---------------------------------------------------------------------
-- Numeración por punto de venta y letra: "FB-1", "FA-1", "R-1", "U-<unidad>"
CREATE TABLE numeradores (
  clave   text PRIMARY KEY,
  ultimo  int NOT NULL DEFAULT 0
);

CREATE TABLE facturas (
  id            serial PRIMARY KEY,
  letra         char(1) NOT NULL CHECK (letra IN ('A','B')),
  pv            int NOT NULL,
  numero        int NOT NULL,
  grupo_id      int NOT NULL REFERENCES grupos(id),
  plan_id       text NOT NULL REFERENCES planes(id),
  periodo       char(7) NOT NULL,                -- 'AAAA-MM'
  emision       date NOT NULL,
  vto           date NOT NULL,
  importe       numeric(12,2) NOT NULL CHECK (importe > 0),
  base          numeric(12,2) NOT NULL,
  promo_pct     int NOT NULL DEFAULT 0,
  promo_mes     int NOT NULL DEFAULT 0,
  promo_meses   int NOT NULL DEFAULT 0,
  promo_hasta   char(7),
  alicuota      numeric(5,2),                    -- NULL = a confirmar
  receptor      jsonb NOT NULL,                  -- {cond,nombre,doc}
  lineas        jsonb NOT NULL,                  -- [{nombre,nro,cat,cuota}]
  cae           text,
  cae_vto       date,
  cae_estado    text NOT NULL DEFAULT 'pendiente' CHECK (cae_estado IN ('pendiente','autorizada','rechazada')),
  cae_error     text,
  estado        text NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente','pagada')),
  mail_estado   text CHECK (mail_estado IN ('encolado','enviado','rebotado')),
  mail_fecha    timestamptz,
  usuario       text NOT NULL DEFAULT '',
  creado        timestamptz NOT NULL DEFAULT now(),
  UNIQUE (grupo_id, periodo),
  UNIQUE (letra, pv, numero)
);
CREATE INDEX facturas_estado ON facturas (estado, vto);
CREATE INDEX facturas_periodo ON facturas (periodo);

CREATE TABLE recibos (
  id          serial PRIMARY KEY,
  pv          int NOT NULL,
  numero      int NOT NULL,
  factura_id  int NOT NULL UNIQUE REFERENCES facturas(id),   -- un recibo cancela una factura entera
  grupo_id    int NOT NULL REFERENCES grupos(id),
  fecha       date NOT NULL,
  importe     numeric(12,2) NOT NULL,
  medio       text NOT NULL,
  masivo      boolean NOT NULL DEFAULT false,
  usuario     text NOT NULL DEFAULT '',                       -- uso interno (RT-10)
  mail_estado text CHECK (mail_estado IN ('encolado','enviado','rebotado')),
  creado      timestamptz NOT NULL DEFAULT now(),
  UNIQUE (pv, numero)
);
CREATE INDEX recibos_grupo ON recibos (grupo_id, fecha);

-- ---------------------------------------------------------------------
-- Beneficios: autorizaciones, usos en unidades y consumos en comercios
-- ---------------------------------------------------------------------
CREATE SEQUENCE autorizacion_nro_seq START 1001;

CREATE TABLE autorizaciones (
  id              serial PRIMARY KEY,
  nro             int NOT NULL UNIQUE DEFAULT nextval('autorizacion_nro_seq'),
  afiliado_id     int NOT NULL REFERENCES afiliados(id),
  prestacion_cod  text NOT NULL REFERENCES prestaciones(cod),
  fecha           date NOT NULL DEFAULT current_date,
  creado          timestamptz NOT NULL DEFAULT now(),
  -- estado: autorizada | rechazada (emitidas en el momento),
  --         pendiente (en auditoría), aprobada | desaprobada (resueltas)
  estado          text NOT NULL CHECK (estado IN ('autorizada','rechazada','pendiente','aprobada','desaprobada')),
  motivo          text NOT NULL DEFAULT '',
  observacion     text NOT NULL DEFAULT '',
  controles       jsonb NOT NULL DEFAULT '[]',
  consumo         text NOT NULL DEFAULT '',      -- "sin cargo" | "30%" | "sin beneficio"
  usuario         text NOT NULL DEFAULT '',
  usuario_id      int REFERENCES usuarios(id),
  auditor         text,
  respuesta       text,
  resuelta_en     timestamptz
);
ALTER SEQUENCE autorizacion_nro_seq OWNED BY autorizaciones.nro;
CREATE INDEX autorizaciones_estado ON autorizaciones (estado);

CREATE TABLE usos (
  id               serial PRIMARY KEY,
  numero           text NOT NULL UNIQUE,          -- U00004-00000012
  unidad_id        int NOT NULL REFERENCES unidades(id),
  afiliado_id      int NOT NULL REFERENCES afiliados(id),
  grupo_id         int NOT NULL REFERENCES grupos(id),
  plan_id          text NOT NULL REFERENCES planes(id),
  fecha            date NOT NULL DEFAULT current_date,
  hora             time NOT NULL DEFAULT localtime(0),
  usuario          text NOT NULL DEFAULT '',
  autorizacion_id  int UNIQUE REFERENCES autorizaciones(id),
  pendiente_unidad boolean NOT NULL DEFAULT false,
  confirmo         text,
  fecha_conf       date,
  saldos           jsonb NOT NULL DEFAULT '[]',
  creado           timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX usos_afiliado ON usos (afiliado_id, fecha);
CREATE INDEX usos_grupo ON usos (grupo_id, fecha);

CREATE TABLE usos_lineas (
  id              serial PRIMARY KEY,
  uso_id          int NOT NULL REFERENCES usos(id) ON DELETE CASCADE,
  prestacion_cod  text NOT NULL REFERENCES prestaciones(cod),
  nombre          text NOT NULL,
  cantidad        int NOT NULL CHECK (cantidad > 0),
  gratis          int NOT NULL DEFAULT 0,
  pct             numeric(5,2) NOT NULL DEFAULT 0,
  precio          numeric(12,2) NOT NULL DEFAULT 0,   -- precio unitario (informado o de referencia)
  precio_informado boolean NOT NULL DEFAULT false,
  lista           numeric(12,2) NOT NULL DEFAULT 0,
  ahorro          numeric(12,2) NOT NULL DEFAULT 0,
  total           numeric(12,2) NOT NULL DEFAULT 0
);
CREATE INDEX usos_lineas_uso ON usos_lineas (uso_id);

CREATE TABLE consumos_comercio (
  id           serial PRIMARY KEY,
  fecha        date NOT NULL DEFAULT current_date,
  hora         time NOT NULL DEFAULT localtime(0),
  usuario_id   int REFERENCES usuarios(id),
  comercio     text NOT NULL,
  rubro        text NOT NULL,
  afiliado_id  int NOT NULL REFERENCES afiliados(id),
  detalle      text NOT NULL DEFAULT '',
  importe      numeric(12,2) NOT NULL DEFAULT 0,
  pct          numeric(5,2) NOT NULL DEFAULT 0,
  ahorro       numeric(12,2) NOT NULL DEFAULT 0,
  total        numeric(12,2) NOT NULL DEFAULT 0,
  creado       timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX consumos_afiliado ON consumos_comercio (afiliado_id, fecha);

-- ---------------------------------------------------------------------
-- Comercial: consultas y promociones (CU-15 a CU-19)
-- ---------------------------------------------------------------------
CREATE TABLE consultas (
  id          serial PRIMARY KEY,
  fecha       date NOT NULL DEFAULT current_date,
  hora        time NOT NULL DEFAULT localtime(0),
  usuario     text NOT NULL DEFAULT '',
  canal       text NOT NULL DEFAULT 'WhatsApp',
  tipo        text NOT NULL DEFAULT 'Cotización',
  afiliado_id int REFERENCES afiliados(id),
  nombre      text NOT NULL DEFAULT '',
  celular     text NOT NULL DEFAULT '',
  planes      text[] NOT NULL DEFAULT '{}',
  como        text NOT NULL DEFAULT 'PDF',
  creado      timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE promociones (
  id         serial PRIMARY KEY,
  titulo     text NOT NULL,
  beneficio  text NOT NULL,
  unidad     text NOT NULL DEFAULT '',          -- '' = Integral en general
  desde      date NOT NULL,
  hasta      date NOT NULL,
  texto      text NOT NULL DEFAULT '',
  cond       text NOT NULL DEFAULT '',
  planes     text[] NOT NULL DEFAULT '{}',      -- vacío = todos
  creada     date NOT NULL DEFAULT current_date,
  por        text NOT NULL DEFAULT '',
  CHECK (hasta >= desde)
);

CREATE TABLE promociones_envios (
  promocion_id int NOT NULL REFERENCES promociones(id) ON DELETE CASCADE,
  grupo_id     int NOT NULL REFERENCES grupos(id),
  fecha        timestamptz NOT NULL DEFAULT now(),
  canal        text NOT NULL CHECK (canal IN ('Mail','WhatsApp')),
  PRIMARY KEY (promocion_id, grupo_id)
);

-- CU-36: un resumen por grupo y mes
CREATE TABLE resumenes_enviados (
  grupo_id  int NOT NULL REFERENCES grupos(id),
  periodo   char(7) NOT NULL,
  canal     text NOT NULL,
  fecha     timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (grupo_id, periodo)
);

-- ---------------------------------------------------------------------
-- Gestión: egresos (CU-33)
-- ---------------------------------------------------------------------
CREATE TABLE egresos (
  id           serial PRIMARY KEY,
  fecha        date NOT NULL,
  categoria    text NOT NULL,
  proveedor    text NOT NULL DEFAULT '',
  cuit         text NOT NULL DEFAULT '',
  detalle      text NOT NULL DEFAULT '',
  comprobante  text NOT NULL DEFAULT 'Sin comprobante',
  numero       text NOT NULL DEFAULT '',
  importe      numeric(12,2) NOT NULL CHECK (importe > 0),
  iva          numeric(12,2) NOT NULL DEFAULT 0 CHECK (iva >= 0),
  medio        text NOT NULL DEFAULT 'Efectivo',
  fijo         boolean NOT NULL DEFAULT false,
  usuario      text NOT NULL DEFAULT '',
  creado       timestamptz NOT NULL DEFAULT now(),
  CHECK (iva < importe)
);
CREATE INDEX egresos_fecha ON egresos (fecha);

-- ---------------------------------------------------------------------
-- Control: cambios de configuración, historial, cola, copias, avisos
-- ---------------------------------------------------------------------
CREATE TABLE config_cambios (
  id        serial PRIMARY KEY,
  fecha     timestamptz NOT NULL DEFAULT now(),
  usuario   text NOT NULL,
  seccion   text NOT NULL,
  cambios   jsonb NOT NULL,                     -- [{t, antes, despues, alerta}]
  snapshot  jsonb NOT NULL,                     -- estado ANTERIOR de la configuración
  deshecho  timestamptz
);

-- RT-07: solo agregado. integral_app no tiene UPDATE ni DELETE.
CREATE TABLE historial (
  id       bigserial PRIMARY KEY,
  fecha    timestamptz NOT NULL DEFAULT now(),
  accion   text NOT NULL,
  detalle  text NOT NULL DEFAULT '',
  usuario  text NOT NULL DEFAULT '',
  autorizo text NOT NULL DEFAULT ''
);
CREATE INDEX historial_fecha ON historial (fecha DESC);

-- ADR-06: cola de tareas en la misma base
CREATE TABLE cola_tareas (
  id              bigserial PRIMARY KEY,
  tipo            text NOT NULL,                -- cae | mail
  payload         jsonb NOT NULL DEFAULT '{}',
  estado          text NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente','en_curso','hecha','fallida')),
  intentos        int NOT NULL DEFAULT 0,
  max_intentos    int NOT NULL DEFAULT 8,
  ejecutar_desde  timestamptz NOT NULL DEFAULT now(),
  error           text,
  creado          timestamptz NOT NULL DEFAULT now(),
  actualizado     timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX cola_pendientes ON cola_tareas (estado, ejecutar_desde) WHERE estado IN ('pendiente','en_curso');

CREATE TABLE backups_estado (
  id       serial PRIMARY KEY,
  fecha    timestamptz NOT NULL DEFAULT now(),
  ok       boolean NOT NULL,
  origen   text NOT NULL DEFAULT 'automatica',  -- automatica | descarga
  detalle  text NOT NULL DEFAULT '',
  usuario  text NOT NULL DEFAULT ''
);

-- Avisos para administradores (CAE rechazado, mail rebotado, certificado por vencer)
CREATE TABLE avisos (
  id      serial PRIMARY KEY,
  fecha   timestamptz NOT NULL DEFAULT now(),
  nivel   text NOT NULL DEFAULT 'warn' CHECK (nivel IN ('info','warn','bad')),
  texto   text NOT NULL,
  leido   timestamptz
);

-- ---------------------------------------------------------------------
-- Permisos de los roles de la aplicación
-- ---------------------------------------------------------------------
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO integral_app;
REVOKE UPDATE, DELETE, TRUNCATE ON historial FROM integral_app;
REVOKE INSERT, UPDATE, DELETE ON schema_migraciones FROM integral_app;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO integral_app;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO integral_backup;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO integral_backup;
GRANT INSERT ON backups_estado TO integral_backup;
GRANT USAGE ON SEQUENCE backups_estado_id_seq TO integral_backup;
