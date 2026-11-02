-- =====================================================================
-- Lotes de prueba · L08 · Promociones (CU-18, CU-19)
-- =====================================================================
--   · "Llegó el verano" (de los datos iniciales): vigente, Óptica
--   · "Mes del corazón": programada (empieza en 10 días), solo Plan Plus
--   · "Invierno saludable": terminada ayer (no se puede enviar)
--   · Un envío por WhatsApp ya hecho de "Llegó el verano"
-- =====================================================================

UPDATE promociones SET desde = date_trunc('month', current_date)::date, hasta = (date_trunc('month', current_date) + interval '2 month - 1 day')::date WHERE titulo = 'Llegó el verano';

INSERT INTO promociones (titulo, beneficio, unidad, desde, hasta, texto, cond, planes, por) VALUES
 ('Mes del corazón', 'Electrocardiograma sin cargo', 'CMDE', current_date + 10, current_date + 40, 'Control cardiológico con electrocardiograma sin cargo para los afiliados del Plan Plus.', 'Con turno previo.', '{PLUS}', 'Santiago'),
 ('Invierno saludable', '20% en vacunas', '', current_date - 60, current_date - 1, 'Vacuna antigripal con descuento.', '', '{}', 'Katy');

INSERT INTO promociones_envios (promocion_id, grupo_id, canal)
SELECT p.id, a.grupo_id, 'WhatsApp' FROM promociones p, afiliados a WHERE p.titulo = 'Llegó el verano' AND a.nro = 91;
