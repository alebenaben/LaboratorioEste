<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Datos fijos tomados de la maqueta v82 (no cambian desde la pantalla).
 * Generado a partir de las constantes de "Integral del Este - demo v82.html".
 */
final class CatalogoMaqueta
{
    /** Versiones del sistema (CU-52), la más nueva primero. */
    public const VERSIONES = array (
  0 => 
  array (
    'v' => 82,
    'fecha' => '2026-10-28',
    't' => 'Nuevo acceso “Quiere darse de baja” para los usuarios: buscan al afiliado y ven todo (antigüedad, cuota, ahorro, lo que usó y lo que tiene sin usar, grupo, cumpleaños, pedidos anteriores). Eligen el motivo y el sistema les dice qué decirle y qué ofrecer, de a una. Los descuentos y cambios de plan los decide administración; las bajas las confirma el administrador desde Estadísticas.',
  ),
  1 => 
  array (
    'v' => 81,
    'fecha' => '2026-10-28',
    't' => 'Nueva sección Promociones (solo administrador): se arma la promoción (título, beneficio, unidad, fechas, a qué planes), se ve el PDF y se manda a todos los afiliados por mail juntos o por WhatsApp de a uno, con el PDF personalizado. Se lleva la cuenta de a quién ya le llegó. La unidad ve la promoción vigente al verificar al afiliado.',
  ),
  2 => 
  array (
    'v' => 80,
    'fecha' => '2026-10-28',
    't' => 'Beneficios: el cuadro de consultas se simplificó a “Enviar información a una persona”: se elige si es un afiliado o un interesado, se completa quién es y el celular, y se manda un PDF prolijo por WhatsApp. Se sacaron el canal, el texto editable y los botones de copiar e imprimir. En todo el sistema ya no se manda texto suelto: cotizaciones, beneficios, resúmenes, ahorro y saludos de cumpleaños salen siempre en PDF.',
  ),
  3 => 
  array (
    'v' => 79,
    'fecha' => '2026-10-28',
    't' => 'Fecha de nacimiento en el alta (titular y familiares) y en la ficha, con la edad. Aviso de cumpleaños del día con saludo por WhatsApp. Retención de bajas: antes de dar de baja se registra el motivo, se le recuerda al afiliado lo que ya obtuvo y se le ofrecen propuestas de a una, de menor a mayor (no se ofrece lo mejor de entrada); cada paso queda registrado y en Estadísticas se ve cuántos se retuvieron y con qué.',
  ),
  4 => 
  array (
    'v' => 78,
    'fecha' => '2026-10-28',
    't' => 'El usuario CMDE pasa a ser un usuario de atención: ve Altas, Cotizador, Autorizaciones y Beneficios, y desde Autorizaciones emite las órdenes para Biobárica, Óptica y demás (que descuentan el beneficio). Nuevo: Resumen mensual de beneficios para los afiliados: al terminar el mes, a cada afiliado que usó algún beneficio se le manda por mail (a todos de una vez) o por WhatsApp (con PDF) el detalle de lo que usó y cuánto ahorró. Aviso a los administradores cuando hay un resumen para enviar.',
  ),
  5 => 
  array (
    'v' => 77,
    'fecha' => '2026-10-28',
    't' => 'Cotizador: para enviar la cotización hay que completar el apellido o nombre del interesado y un celular válido (10 números con característica). Si falta algo, el casillero se marca en rojo y se explica qué falta.',
  ),
  6 => 
  array (
    'v' => 76,
    'fecha' => '2026-10-28',
    't' => 'Cotizador: el interesado se escribe como “Apellido y nombre” y queda en mayúsculas. El sistema reconoce solo cuál es el apellido y cuáles los nombres (aunque se escriban al revés) y saluda por el primer nombre.',
  ),
  7 => 
  array (
    'v' => 75,
    'fecha' => '2026-10-28',
    't' => 'Cotizador: casilleros para el nombre y el celular del interesado. Con el celular cargado, “Enviar cotización en PDF” va directo a su WhatsApp y el mensaje lo saluda por su nombre. Cada cotización enviada queda registrada como consulta.',
  ),
  8 => 
  array (
    'v' => 74,
    'fecha' => '2026-10-28',
    't' => 'Enviar por WhatsApp desde el Cotizador, Beneficios o la ficha: ahora lo principal es el PDF prolijo (botón grande “Compartir PDF por WhatsApp”) con un mensaje corto que lo acompaña. Mandar solo el texto queda como opción secundaria.',
  ),
  9 => 
  array (
    'v' => 73,
    'fecha' => '2026-10-28',
    't' => 'Mejoras sugeridas por administración: 1) Mail: el email del titular es obligatorio en el alta (salvo que no tenga), las facturas del mes se envían por mail a todos de una vez con un texto que se arma en Configuración, y recibos y facturas tienen botón Enviar por mail. 2) Promoción en la cuota: se puede renovar, extender o quitar desde la ficha del afiliado. 3) Débito automático uno por uno, con la fecha en que entró cada pago (Mercado Pago cobra en fechas distintas). 4) Autorizaciones: al emitir una autorización se descuenta el beneficio (sesiones sin cargo, cupos), igual que cuando lo registra la unidad, sin contarlo dos veces.',
  ),
  10 => 
  array (
    'v' => 72,
    'fecha' => '2026-10-28',
    't' => 'Las facturas y los recibos que recibe el afiliado (en pantalla, impresos o en PDF) ya no dicen quién cobró ni quién emitió. Ese dato queda solo para uso interno (listas de Caja e historial).',
  ),
  11 => 
  array (
    'v' => 71,
    'fecha' => '2026-10-28',
    't' => 'PDF prolijos para mandar a las personas: información de un plan, cotización (integrantes, cuota, promoción y beneficios), situación del afiliado (lo que tiene disponible, lo que usó y cuánto ahorró) y la consulta armada en Beneficios. En cada envío por WhatsApp aparecen los botones Descargar PDF y Compartir PDF. Lo puede usar cualquier usuario.',
  ),
  12 => 
  array (
    'v' => 70,
    'fecha' => '2026-10-28',
    't' => 'Recibos y facturas de cuota en PDF prolijo, con el logo y los datos de Grupo del Este Salud S.A.S.: botón Descargar PDF y botón Compartir PDF (en el celular abre directamente WhatsApp, mail u otras apps con el PDF adjunto; en la computadora lo descarga y abre WhatsApp para adjuntarlo).',
  ),
  13 => 
  array (
    'v' => 69,
    'fecha' => '2026-10-28',
    't' => 'Ingreso: mensajes más claros (usuario inexistente, clave incorrecta o usuario dado de baja) y, solo en la demo, un enlace para volver las claves de todos los usuarios a su nombre si alguien no puede entrar. El teclado del celular ya no cambia mayúsculas en el usuario.',
  ),
  14 => 
  array (
    'v' => 68,
    'fecha' => '2026-10-28',
    't' => 'Recibos de cobro: al tocar Cobrar se abre el recibo listo para imprimir o mandar por WhatsApp (con el importe en letras, la factura que cancela y el medio de pago). En Caja hay una lista de recibos emitidos y en la cuenta corriente cada recibo tiene su botón Ver.',
  ),
  15 => 
  array (
    'v' => 67,
    'fecha' => '2026-10-28',
    't' => 'Salir más visible: botón “Salir” con ícono, pegado al nombre, arriba a la derecha en todas las pantallas (administradores, usuarios y unidades). También en el recuadro del usuario del menú.',
  ),
  16 => 
  array (
    'v' => 66,
    'fecha' => '2026-10-28',
    't' => 'Configuración → Copias de seguridad: un administrador descarga una copia completa de los datos (afiliados, cuotas, facturas, usos, egresos, usuarios y configuración) y la puede restaurar con su clave. Muestra cuándo se hizo la última copia y avisa en rojo si pasaron más de 7 días. En el servidor las copias van a ser automáticas.',
  ),
  17 => 
  array (
    'v' => 65,
    'fecha' => '2026-10-28',
    't' => 'Se eliminó la unidad de prueba Kinesiología, con su usuario, sus prestaciones y sus usos de prueba.',
  ),
  18 => 
  array (
    'v' => 64,
    'fecha' => '2026-10-28',
    't' => 'Unidades dadas de baja: desaparecen de la lista de unidades y quedan en un apartado aparte (“Ver unidades dadas de baja”). Al abrir una dada de baja solo se puede volver a activarla o eliminarla (si nunca registró usos); ya no ofrece cambiar usuario ni clave.',
  ),
  19 => 
  array (
    'v' => 63,
    'fecha' => '2026-10-28',
    't' => 'Ingreso: la lista de usuarios ya no muestra a todos. Cada computadora recuerda solo los usuarios que entraron desde ella (el último primero) y se pueden olvidar con la ×. Quien entra por primera vez escribe su usuario.',
  ),
  20 => 
  array (
    'v' => 62,
    'fecha' => '2026-10-28',
    't' => 'Unidades de negocio editables: botón Editar en cada unidad para cambiar el nombre (las creadas por ustedes), el texto del beneficio, la carencia, el usuario y la clave. Se puede dar de baja una unidad (deja de poder entrar y verificar, pero su historial queda) y volver a activarla, o eliminarla si nunca registró usos. Todo se confirma con la clave del administrador.',
  ),
  21 => 
  array (
    'v' => 61,
    'fecha' => '2026-10-28',
    't' => 'Configuración más segura: antes de guardar cualquier cambio (precios, descuentos, sesiones sin cargo, carencias, permisos, puntos de venta, prestaciones) el sistema muestra la lista exacta de lo que va a cambiar, antes y después, marca en rojo los cambios fuera de lo común y pide la clave del administrador para confirmar. Los campos modificados se marcan en amarillo mientras se editan. Cada cambio se puede deshacer desde Configuración → Últimos cambios.',
  ),
  22 => 
  array (
    'v' => 60,
    'fecha' => '2026-10-28',
    't' => 'Nueva sección Informes (administradores): se elige el informe (movimientos de caja, facturación de cuotas, egresos, libro diario, usos de beneficios, altas y bajas, padrón o resumen estadístico), el período (este mes, mes anterior, un mes puntual, este año, año anterior o entre dos fechas) y se descarga en Excel o PDF. Antes de descargar se ve una vista previa.',
  ),
  23 => 
  array (
    'v' => 59,
    'fecha' => '2026-10-28',
    't' => 'Nueva sección Egresos (administradores): se cargan los gastos de Integral del Este (alquiler, sueldos, insumos, servicios, honorarios, sepelio, ayudas a afiliados, publicidad, comisiones, impuestos, etc.) con proveedor, comprobante, IVA y medio de pago. Gastos fijos que se repiten cada mes con un clic. Resumen del mes: ingresos cobrados, egresos por categoría y resultado. Cada egreso genera su asiento en Contabilidad.',
  ),
  24 => 
  array (
    'v' => 58,
    'fecha' => '2026-10-28',
    't' => 'Estadísticas → Consumo de beneficios: qué usan los afiliados en cada unidad y comercio (usos, sin cargo, con descuento, afiliados distintos y ahorro), por plan, lo más usado y los beneficios que nadie usó en el período. Incluye los consumos cargados por cualquier usuario de unidad o comercio. Botón para ver cada uso en detalle.',
  ),
  25 => 
  array (
    'v' => 57,
    'fecha' => '2026-10-28',
    't' => 'Ahorro de los afiliados: en Estadísticas se ve cuánto ahorraron los afiliados por usar sus beneficios (este mes, últimos 3 meses o este año), por unidad y por grupo, comparado con lo que pagaron de cuota, con un botón para mandarle a cada titular su resumen de ahorro por WhatsApp. La unidad ve cuánto lleva ahorrado el afiliado en el año, y la ficha del afiliado también. Si la unidad no carga el precio, se usa el valor de referencia del nomenclador.',
  ),
  26 => 
  array (
    'v' => 56,
    'fecha' => '2026-10-28',
    't' => 'Configuración → Unidades de negocio: se puede crear una unidad nueva con su nombre, el servicio que presta, el descuento para cada plan, la carencia y, si corresponde, prestaciones sin cargo. Se crea sola con su usuario (clave igual al nombre) y funciona igual que Biobárica, Laboratorio u Óptica. También se ve el resumen de todas las unidades.',
  ),
  27 => 
  array (
    'v' => 55,
    'fecha' => '2026-10-28',
    't' => 'Unidades de negocio: el afiliado se puede buscar también por nombre, apellido o CUIT, con sugerencias desde la primera letra. El DNI sigue con separador de miles.',
  ),
  28 => 
  array (
    'v' => 54,
    'fecha' => '2026-10-28',
    't' => 'Usuarios de unidades bien escritos: Biobárica, Laboratorio, Óptica y CMDE. Se puede entrar escribiéndolos con o sin tilde y mayúsculas; la clave sigue siendo el mismo nombre.',
  ),
  29 => 
  array (
    'v' => 53,
    'fecha' => '2026-10-28',
    't' => 'Unidades de negocio: apenas se escribe el DNI aparece el afiliado y, destacado, de qué beneficio dispone hoy en esa unidad según su plan (sin cargo con cuántas le quedan, o el % de descuento), cuándo mejora el descuento si es escalonado y el beneficio completo de su plan.',
  ),
  30 => 
  array (
    'v' => 52,
    'fecha' => '2026-10-28',
    't' => 'Verificar afiliado (unidades y comercios): el DNI se escribe con separador de miles y Esc borra todo para verificar a otro afiliado.',
  ),
  31 => 
  array (
    'v' => 51,
    'fecha' => '2026-10-28',
    't' => 'Ingreso: la lista de usuarios muestra también las unidades de negocio (Biobárica, Laboratorio, Óptica, CMDE), marcadas como unidad. Los comercios adheridos siguen sin aparecer en la lista: escriben su usuario.',
  ),
  32 => 
  array (
    'v' => 50,
    'fecha' => '2026-10-28',
    't' => 'Laboratorio pasa a ser unidad de negocio, como la Biobárica y la Óptica: verifica al afiliado y registra los usos (análisis de laboratorio con hasta 30% de reintegro, carencia de 6 meses, en los planes Integral y Plus). Usuarios de unidad: biobarica, laboratorio y optica (clave igual al nombre).',
  ),
  33 => 
  array (
    'v' => 49,
    'fecha' => '2026-10-28',
    't' => 'Integral del Este solo factura las cuotas de sus afiliados. Las prestaciones las cobra cada unidad con su propio sistema: la sección Prestaciones pasa a ser Usos en unidades, solo para administradores, con lo que registraron la Biobárica, la Óptica y el CMDE (sin cargo, descuentos y ahorro). Los usuarios ya no ven Prestaciones. En Configuración, las prestaciones quedan con su valor de referencia (sin IVA ni facturación) y el punto de venta es solo el de las cuotas.',
  ),
  34 => 
  array (
    'v' => 48,
    'fecha' => '2026-10-28',
    't' => 'Usuarios para las unidades de negocio (Biobárica, Óptica y CMDE): con el DNI o el N° de afiliado ven si está vigente y qué le corresponde hoy en cada prestación (sin cargo con cuántas le quedan, o el % de descuento), y registran el uso. El registro queda en el historial del afiliado en Integral y descuenta del cupo: la próxima vez el sistema ya sabe cuántas usó. La unidad cobra con su propio sistema.',
  ),
  35 => 
  array (
    'v' => 47,
    'fecha' => '2026-10-28',
    't' => 'Importar precios desde Excel: si el archivo tiene una hoja llamada Nomenclador (o Precios / Lista), se lee solo esa y se ignoran las hojas de instrucciones. Pensado para el nomenclador de prestaciones que completa cada unidad.',
  ),
  36 => 
  array (
    'v' => 46,
    'fecha' => '2026-10-28',
    't' => 'IVA según el contador: cuotas de los planes (prepaga, afiliados voluntarios) gravadas al 10,5%; prestaciones a pacientes particulares al 21%. Obras sociales sindicales, prepagas por afiliados obligatorios y hospitales públicos, exentos. Factura A solo a Responsables Inscriptos; Factura B a consumidores finales y exentos. La tabla queda en Configuración → Tratamiento de IVA.',
  ),
  37 => 
  array (
    'v' => 45,
    'fecha' => '2026-10-28',
    't' => 'IVA: cada plan y cada prestación tiene su alícuota (21%, 10,5%, exento o a confirmar). Factura A para quien paga como Responsable Inscripto, con neto e IVA discriminado; Factura B con la leyenda del Régimen de Transparencia Fiscal al Consumidor (Ley 27.743) y el IVA contenido. Los datos fiscales de quien paga (condición frente al IVA, CUIT y razón social) se cargan en la ficha del titular. Factura A y B llevan numeración separada.',
  ),
  38 => 
  array (
    'v' => 44,
    'fecha' => '2026-10-28',
    't' => 'Puntos de venta independientes: cuotas de Integral, CMDE, Óptica y Biobárica tienen cada uno su punto de venta y su numeración de facturas (formato 00001-00000001). Se configuran en Configuración → Puntos de venta, con el último número emitido para seguir la numeración.',
  ),
  39 => 
  array (
    'v' => 43,
    'fecha' => '2026-10-28',
    't' => 'Facturas y recibos con los datos fiscales reales de Grupo del Este Salud S.A.S.: CUIT 30-71644749-5, IVA Responsable Inscripto, Ingresos Brutos ATER contribuyente directo, inicio de actividades 05/2019 y domicilio Gral. José Gervasio Artigas 371, Colonia Avellaneda (CP 3107), Entre Ríos. Encabezado con la letra del comprobante (B o X) y receptor Consumidor Final.',
  ),
  40 => 
  array (
    'v' => 42,
    'fecha' => '2026-10-27',
    't' => 'Configuración → Prestaciones: cada precio muestra la fecha de su última actualización y quién la hizo, y arriba se ve la fecha de la última actualización de la lista. Se registra al guardar a mano y al importar desde PDF o Excel.',
  ),
  41 => 
  array (
    'v' => 41,
    'fecha' => '2026-10-27',
    't' => 'Actualizar prestaciones desde un archivo: en Configuración → Prestaciones se sube el PDF (o Excel) con la lista de precios. El sistema lee cada renglón, lo relaciona con la prestación que ya existe y muestra una vista previa con precio actual y nuevo, prestaciones nuevas para agregar y las que no figuran en la lista para quitar. Se puede corregir todo antes de aplicar y queda registrado en el historial.',
  ),
  42 => 
  array (
    'v' => 40,
    'fecha' => '2026-10-27',
    't' => 'Prestaciones: el total con los botones (factura, recibo, guardar) queda arriba a la derecha, justo debajo de la prestación elegida, más compacto y con borde azul. Se ve apenas se elige el afiliado y la prestación, sin bajar la pantalla.',
  ),
  43 => 
  array (
    'v' => 39,
    'fecha' => '2026-10-27',
    't' => 'Prestaciones: en todas se puede elegir Factura o Recibo. Con Recibo (no se factura) se puede aplicar un descuento adicional en porcentaje, que se suma al beneficio del plan y se ve en el recibo. Si lo aplica un usuario, lo tiene que autorizar un administrador (se puede cambiar en Configuración → Permisos).',
  ),
  44 => 
  array (
    'v' => 38,
    'fecha' => '2026-10-27',
    't' => 'Prestaciones sin cargo: si el afiliado no tiene que pagar nada no se hace factura. Se ve el cartel Sin cargo, sin medio de pago, y se elige Guardar (queda registrado el uso) o Guardar e imprimir recibo. El comprobante se llama Recibo sin cargo y no lleva CAE.',
  ),
  45 => 
  array (
    'v' => 37,
    'fecha' => '2026-10-27',
    't' => 'Prestaciones: cuando la prestación entra en lo sin cargo del plan ya no aparece el casillero de descuento; el descuento solo se muestra para las que se cobran (la segunda en adelante). Tampoco aparece si el afiliado no está al día o ya llegó al tope. La línea sin cargo se marca en verde y la factura no le suma descuento a lo que fue gratis.',
  ),
  46 => 
  array (
    'v' => 36,
    'fecha' => '2026-10-27',
    't' => 'Beneficios: nuevo panel Atender una consulta para quien llama, escribe o viene en persona. Se elige el canal, si es afiliado (se envía su situación y lo que le queda) o qué planes le interesan, y al final se pone el teléfono y se envía por WhatsApp. También se puede imprimir o copiar. Cada consulta queda registrada y se ve en Estadísticas. Desde acá solo se informa: las decisiones quedan en administración.',
  ),
  47 => 
  array (
    'v' => 35,
    'fecha' => '2026-10-27',
    't' => 'Los comercios adheridos cargan el consumo de cada afiliado: detalle, importe y descuento aplicado (con tope por rubro). Se pueden fijar límites de usos por mes, semestre o año. Estadísticas de consumo por comercio y por afiliado, y consumos en la ficha del afiliado.',
  ),
  48 => 
  array (
    'v' => 34,
    'fecha' => '2026-10-27',
    't' => 'Usuarios para comercios adheridos: cada comercio (gimnasios, Leguan, Pronoia, barbería, laboratorio) entra con su usuario y solo puede verificar por DNI o N° si una persona es afiliada y tiene su descuento. Puede registrar el uso, que se ve en Estadísticas.',
  ),
  49 => 
  array (
    'v' => 33,
    'fecha' => '2026-10-27',
    't' => 'Auditoría de autorizaciones: los usuarios pueden enviar una prestación o beneficio a auditoría con una observación; los administradores la aprueban o rechazan en la nueva sección Auditoría. Aviso al ingresar y franja roja mientras haya pendientes. Cada prestación puede marcarse como Requiere auditoría.',
  ),
  50 => 
  array (
    'v' => 32,
    'fecha' => '2026-10-27',
    't' => 'Cotizador: se elige el plan a cotizar (Plus, Integral, Salud o Sepelio) desde una barra arriba o tocando su tarjeta. Botón verde Enviar cotización por WhatsApp arriba, con el plan elegido, los integrantes, el total, la promoción y los beneficios.',
  ),
  51 => 
  array (
    'v' => 31,
    'fecha' => '2026-10-27',
    't' => 'Cuando hay una promoción aplicada, se ve en rojo arriba a la derecha del Cotizador y de Nueva alta, y el botón Promoción queda en rojo.',
  ),
  52 => 
  array (
    'v' => 30,
    'fecha' => '2026-10-27',
    't' => 'Precios nuevos: Plan Integral $15.000 y Plan Plus $25.000 (+64 años, $4.000 más). Integrantes adicionales por edad: menor de 18 con 25%, de 18 a 49 con 20%, de 50 a 64 con 10% y +64 sin descuento. Nuevos planes individuales: Plan Salud $10.000 y Plan Sepelio $10.000 ($14.000 +64 años).',
  ),
  53 => 
  array (
    'v' => 29,
    'fecha' => '2026-10-27',
    't' => 'Se cargaron los beneficios detallados del PDF: nueva sección Beneficios con buscador, carencias de cada plan, beneficios por mes, semestre o año, sesiones de hiperbárica por grupo y escala de óptica por antigüedad. Botón para enviar por WhatsApp los beneficios del plan o el estado de un afiliado (lo que usó, lo que le queda y sus carencias).',
  ),
  54 => 
  array (
    'v' => 28,
    'fecha' => '2026-10-27',
    't' => 'La factura de la cuota se puede ver e imprimir, con el detalle por integrante y la bonificación de la promoción de alta (porcentaje, monto y mes N de M).',
  ),
  55 => 
  array (
    'v' => 27,
    'fecha' => '2026-10-27',
    't' => 'Promociones: botón Promoción en el Cotizador y en Nueva alta para dar un descuento de 5% a 40% durante 1, 2, 3 o 6 meses. La cotización muestra la cuota con y sin promoción, y la facturación la aplica sola en los meses que corresponde.',
  ),
  56 => 
  array (
    'v' => 26,
    'fecha' => '2026-10-27',
    't' => 'Buscador con sugerencias en todo el sistema: desde la primera letra aparecen las opciones y se van acotando. Funciona para afiliados, apellidos y nombres en las altas, prestaciones, categorías, planes y demás listas.',
  ),
  57 => 
  array (
    'v' => 25,
    'fecha' => '2026-10-27',
    't' => 'En Nueva alta, si el DNI del titular ya está en el padrón, el sistema avisa apenas se termina de escribir y vuelve al inicio. Si es el DNI de un familiar, avisa y lo marca.',
  ),
  58 => 
  array (
    'v' => 24,
    'fecha' => '2026-10-27',
    't' => 'Al confirmar un alta, los usuarios vuelven a la pantalla de inicio y los administradores ven al afiliado nuevo en el padrón.',
  ),
  59 => 
  array (
    'v' => 23,
    'fecha' => '2026-10-27',
    't' => 'Alta y ficha con apellido, primer nombre y segundo nombre por separado, siempre en mayúsculas. Al agregar un familiar se copia el apellido del titular. DNI con separador de miles (28.913.458) y CUIT con guiones (20-34014173-4).',
  ),
  60 => 
  array (
    'v' => 22,
    'fecha' => '2026-10-27',
    't' => 'Accesos del inicio más grandes.',
  ),
  61 => 
  array (
    'v' => 21,
    'fecha' => '2026-10-27',
    't' => 'Inicio de usuarios: logo grande y visible arriba (sin logo de fondo) y los cuatro accesos siempre en una fila horizontal.',
  ),
  62 => 
  array (
    'v' => 20,
    'fecha' => '2026-10-27',
    't' => 'Inicio de usuarios más prolijo: sin menú lateral, barra fina arriba con logo y salir, logo de fondo más visible y las cuatro opciones en una sola fila horizontal.',
  ),
  63 => 
  array (
    'v' => 19,
    'fecha' => '2026-10-27',
    't' => 'Pantalla de inicio para los usuarios: Altas, Cotizador, Autorizaciones y Prestaciones en cuadros centrados sobre el logo de Integral del Este. Con Esc se vuelve al inicio.',
  ),
  64 => 
  array (
    'v' => 18,
    'fecha' => '2026-10-27',
    't' => 'En Prestaciones y Autorizaciones se puede buscar al afiliado por nombre y apellido: desde 3 letras aparecen hasta 6 sugerencias para elegir.',
  ),
  65 => 
  array (
    'v' => 17,
    'fecha' => '2026-10-27',
    't' => 'Los usuarios comunes solo ven Altas, Cotizador, Autorizaciones y Prestaciones. Afiliados, Caja, Cuentas corrientes, Contabilidad, Estadísticas y Configuración son solo para administradores. En Autorizaciones, cada usuario ve solo lo que emitió en el día.',
  ),
  66 => 
  array (
    'v' => 16,
    'fecha' => '2026-10-27',
    't' => 'Historial de beneficios por afiliado: cuánto usó y cuánto le queda de cada beneficio en el año, visible al buscarlo en Prestaciones y Autorizaciones y en su ficha. Las sesiones sin cargo generan una constancia de beneficio, y facturas y constancias imprimen el saldo que le queda.',
  ),
  67 => 
  array (
    'v' => 15,
    'fecha' => '2026-10-27',
    't' => 'Se incorporó el folleto: Plan Integral del Este y Plan Plus del Este con sus beneficios. El Cotizador compara los dos planes. Prestaciones aplica las reglas de cada plan: sesiones sin cargo por año, descuento fijo o "hasta" y carencia (óptica del Plus desde los 3 meses).',
  ),
  68 => 
  array (
    'v' => 14,
    'fecha' => '2026-10-27',
    't' => 'Nueva sección Prestaciones: factura lo atendido en el CMDE, la óptica y la biobárica con el descuento de afiliado. Nueva sección Altas para los usuarios. Los usuarios trabajan en Altas, Cotizador, Autorizaciones y Prestaciones. Las bajas solo las hace un administrador.',
  ),
  69 => 
  array (
    'v' => 13,
    'fecha' => '2026-10-27',
    't' => 'El sistema pide usuario y clave cada vez que se abre, y cierra la sesión solo después de 15 minutos sin uso.',
  ),
  70 => 
  array (
    'v' => 12,
    'fecha' => '2026-10-27',
    't' => 'Privacidad: la lista de afiliados solo la ven los administradores. Los usuarios autorizan buscando por DNI o N° de afiliado, sin ver el padrón.',
  ),
  71 => 
  array (
    'v' => 11,
    'fecha' => '2026-10-27',
    't' => 'Se agregó el usuario Katy (clave inicial: su nombre). Santiago, Mauro y Pablo (dueños) y Katy (administradora) quedan como administradores.',
  ),
  72 => 
  array (
    'v' => 10,
    'fecha' => '2026-10-26',
    't' => 'Botón de ojito en los campos de clave para ver lo que se está escribiendo.',
  ),
  73 => 
  array (
    'v' => 9,
    'fecha' => '2026-10-26',
    't' => 'Al ingresar, el campo Usuario tiene una pestaña que despliega la lista de usuarios para elegir.',
  ),
  74 => 
  array (
    'v' => 8,
    'fecha' => '2026-10-26',
    't' => 'Ingreso con usuario y clave (Santiago, Mauro, Pablo, Belén, Cecilia, Angie, Sofía y Mari). Cada uno puede cambiar su clave. Los administradores dan de alta y de baja usuarios, les cambian la clave, eligen qué secciones ve cada uno y agregan prestaciones a autorizar.',
  ),
  75 => 
  array (
    'v' => 7,
    'fecha' => '2026-10-26',
    't' => 'Punto de partida: el sistema arranca con los 24 afiliados del Excel al día al 31/10/2026, sin deudas ni historial previo. La primera facturación es noviembre 2026.',
  ),
  76 => 
  array (
    'v' => 6,
    'fecha' => '2026-10-26',
    't' => 'Número de versión, Esc para cancelar, separador de miles en los montos y modo administrador con Configuración (precios, permisos, vencimiento, carencias e historial de cambios).',
  ),
  77 => 
  array (
    'v' => 5,
    'fecha' => '2026-10-26',
    't' => 'Las cuotas de septiembre y octubre quedan como historial del sistema anterior (Gecros).',
  ),
  78 => 
  array (
    'v' => 4,
    'fecha' => '2026-10-26',
    't' => 'Se cargó el padrón real del Excel: 24 afiliados del Plan Plus del Este y sus cobranzas.',
  ),
  79 => 
  array (
    'v' => 3,
    'fecha' => '2026-10-26',
    't' => 'Colores suaves en azul y rojo de la marca, letra más grande y más aire.',
  ),
  80 => 
  array (
    'v' => 2,
    'fecha' => '2026-10-26',
    't' => 'Logo y nombre de Integral del Este.',
  ),
  81 => 
  array (
    'v' => 1,
    'fecha' => '2026-10-20',
    't' => 'Primera versión del sistema (prototipo con datos de ejemplo).',
  ),
);

    /** Nombres de pila frecuentes para separar apellido y nombres (CU-15). */
    public const NOMBRES_AR = array (
  0 => 'juan',
  1 => 'jose',
  2 => 'josé',
  3 => 'maria',
  4 => 'maría',
  5 => 'ana',
  6 => 'carlos',
  7 => 'luis',
  8 => 'jorge',
  9 => 'miguel',
  10 => 'pedro',
  11 => 'pablo',
  12 => 'santiago',
  13 => 'mauro',
  14 => 'mateo',
  15 => 'martin',
  16 => 'martín',
  17 => 'diego',
  18 => 'daniel',
  19 => 'alejandro',
  20 => 'alejandra',
  21 => 'fernando',
  22 => 'fernanda',
  23 => 'gabriel',
  24 => 'gabriela',
  25 => 'javier',
  26 => 'sergio',
  27 => 'ricardo',
  28 => 'roberto',
  29 => 'eduardo',
  30 => 'hugo',
  31 => 'raul',
  32 => 'raúl',
  33 => 'oscar',
  34 => 'óscar',
  35 => 'mario',
  36 => 'marcelo',
  37 => 'marcos',
  38 => 'lucas',
  39 => 'lucia',
  40 => 'lucía',
  41 => 'laura',
  42 => 'silvia',
  43 => 'claudia',
  44 => 'patricia',
  45 => 'monica',
  46 => 'mónica',
  47 => 'susana',
  48 => 'graciela',
  49 => 'marta',
  50 => 'norma',
  51 => 'beatriz',
  52 => 'alicia',
  53 => 'elena',
  54 => 'rosa',
  55 => 'carmen',
  56 => 'teresa',
  57 => 'isabel',
  58 => 'cristina',
  59 => 'andrea',
  60 => 'natalia',
  61 => 'carolina',
  62 => 'florencia',
  63 => 'valeria',
  64 => 'veronica',
  65 => 'verónica',
  66 => 'romina',
  67 => 'julieta',
  68 => 'camila',
  69 => 'sofia',
  70 => 'sofía',
  71 => 'valentina',
  72 => 'martina',
  73 => 'agustina',
  74 => 'micaela',
  75 => 'milagros',
  76 => 'antonella',
  77 => 'paula',
  78 => 'belen',
  79 => 'belén',
  80 => 'cecilia',
  81 => 'angie',
  82 => 'mari',
  83 => 'katy',
  84 => 'daniela',
  85 => 'victoria',
  86 => 'emilia',
  87 => 'catalina',
  88 => 'abril',
  89 => 'candela',
  90 => 'lautaro',
  91 => 'thiago',
  92 => 'benjamin',
  93 => 'benjamín',
  94 => 'joaquin',
  95 => 'joaquín',
  96 => 'tomas',
  97 => 'tomás',
  98 => 'facundo',
  99 => 'nicolas',
  100 => 'nicolás',
  101 => 'agustin',
  102 => 'agustín',
  103 => 'federico',
  104 => 'emiliano',
  105 => 'leandro',
  106 => 'ezequiel',
  107 => 'franco',
  108 => 'gonzalo',
  109 => 'matias',
  110 => 'matías',
  111 => 'ivan',
  112 => 'iván',
  113 => 'leonel',
  114 => 'leonela',
  115 => 'desiree',
  116 => 'mabel',
  117 => 'hilda',
  118 => 'nelly',
  119 => 'ramon',
  120 => 'ramón',
  121 => 'ruben',
  122 => 'rubén',
  123 => 'hector',
  124 => 'héctor',
  125 => 'walter',
  126 => 'omar',
  127 => 'nestor',
  128 => 'néstor',
  129 => 'alberto',
  130 => 'angel',
  131 => 'ángel',
  132 => 'antonio',
  133 => 'francisco',
  134 => 'manuel',
  135 => 'rafael',
  136 => 'ignacio',
  137 => 'esteban',
  138 => 'adrian',
  139 => 'adrián',
  140 => 'cesar',
  141 => 'césar',
  142 => 'dario',
  143 => 'darío',
  144 => 'julio',
  145 => 'julian',
  146 => 'julián',
  147 => 'cristian',
  148 => 'christian',
  149 => 'rodrigo',
  150 => 'maximiliano',
  151 => 'sebastian',
  152 => 'sebastián',
  153 => 'enzo',
  154 => 'bautista',
  155 => 'ramiro',
  156 => 'nahuel',
  157 => 'brenda',
  158 => 'yanina',
  159 => 'noelia',
  160 => 'vanesa',
  161 => 'soledad',
  162 => 'lorena',
  163 => 'mariela',
  164 => 'marisa',
  165 => 'sandra',
  166 => 'liliana',
  167 => 'viviana',
  168 => 'gladys',
  169 => 'ines',
  170 => 'inés',
  171 => 'irma',
  172 => 'olga',
  173 => 'estela',
  174 => 'stella',
  175 => 'nora',
  176 => 'elsa',
  177 => 'delia',
  178 => 'ester',
  179 => 'esther',
);

    public const MOTIVOS_BAJA = array (
  0 => 
  array (
    0 => 'economico',
    1 => 'Problemas económicos / la cuota es cara',
  ),
  1 => 
  array (
    0 => 'nouso',
    1 => 'No lo usa / no le encuentra utilidad',
  ),
  2 => 
  array (
    0 => 'otra',
    1 => 'Tiene otra cobertura (obra social o prepaga)',
  ),
  3 => 
  array (
    0 => 'mudanza',
    1 => 'Se muda de la zona',
  ),
  4 => 
  array (
    0 => 'experiencia',
    1 => 'Tuvo una mala experiencia',
  ),
  5 => 
  array (
    0 => 'fallecimiento',
    1 => 'Fallecimiento',
  ),
  6 => 
  array (
    0 => 'otro',
    1 => 'Otro motivo',
  ),
);

    public const INFORMES = array (
  0 => 
  array (
    0 => 'caja',
    1 => 'Movimientos de caja',
    2 => 'Cobros de cuotas y egresos, con saldo acumulado.',
  ),
  1 => 
  array (
    0 => 'facturacion',
    1 => 'Facturación de cuotas',
    2 => 'Facturas emitidas en el período.',
  ),
  2 => 
  array (
    0 => 'egresos',
    1 => 'Egresos',
    2 => 'Gastos cargados en el período.',
  ),
  3 => 
  array (
    0 => 'diario',
    1 => 'Libro diario',
    2 => 'Asientos contables del período.',
  ),
  4 => 
  array (
    0 => 'usos',
    1 => 'Usos de beneficios',
    2 => 'Lo que registraron las unidades de negocio.',
  ),
  5 => 
  array (
    0 => 'altasbajas',
    1 => 'Altas y bajas',
    2 => 'Afiliados que entraron o se fueron en el período.',
  ),
  6 => 
  array (
    0 => 'padron',
    1 => 'Padrón de afiliados',
    2 => 'Afiliados activos hoy (no depende del período).',
  ),
  7 => 
  array (
    0 => 'resumen',
    1 => 'Resumen estadístico',
    2 => 'Indicadores del período en una página.',
  ),
);

    public const TIPO_COMP = array (
  0 => 'Factura A',
  1 => 'Factura B',
  2 => 'Factura C',
  3 => 'Ticket',
  4 => 'Recibo',
  5 => 'Sin comprobante',
);

    public const INICIO_TXT = array (
  'retencion' => 'Te guía para que el afiliado no se vaya',
  'beneficios' => 'Qué incluye cada plan y enviarlo por WhatsApp',
  'altas' => 'Afiliar a una persona o a un grupo familiar',
  'cotizador' => 'Cuánto cuesta cada plan y qué incluye',
  'autorizaciones' => 'Verificar si el afiliado puede atenderse',
  'prestaciones' => 'Facturar con el beneficio del plan',
);
}
