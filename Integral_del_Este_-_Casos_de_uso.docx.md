# **Integral del Este — Casos de uso**

Oct 29, 2026 · @Ale

Son 52 casos de uso en 7 módulos, derivados de las 18 pantallas de la maqueta "Integral del Este \- demo v82.html". Cada regla cita el comportamiento de la maqueta; lo que la maqueta no resuelve está marcado como SUPUESTO o PREGUNTA ABIERTA (al final). La arquitectura de referencia está en el documento "Integral del Este — Arquitectura".

## **1\. Actores y roles**

Hay cuatro roles que ingresan al sistema; el resto son actores externos que solo reciben información. El rol se guarda en el usuario y la API lo valida en cada pedido.

| Actor | Tipo | Usuarios en la maqueta | Qué ve y hace | Pantalla al ingresar |
| :---- | :---- | :---- | :---- | :---- |
| Administrador | Rol interno | Santiago, Mauro, Pablo, Katy | Todas las secciones. Es el único que da de baja, cobra, factura, audita y configura. | Afiliados |
| Usuario de atención | Rol interno | Belén, Cecilia, Angie, Sofía, Mari, CMDE | Solo las secciones que le habilita un administrador, entre: Altas, Cotizador, Autorizaciones, Beneficios y Quiere darse de baja. | Inicio (accesos grandes) |
| Unidad de negocio | Rol externo con acceso | Biobárica, Laboratorio, Óptica (y las que se creen) | Solo "Verificar afiliado" en modo unidad: ve qué le corresponde hoy al afiliado y registra el uso. | Verificar afiliado |
| Comercio adherido | Rol externo con acceso | sanbenito, fitform, leguan, pronoia, barberia | Solo "Verificar afiliado" en modo comercio: confirma el beneficio por DNI o N° y carga el consumo. | Verificar afiliado |
| Sistema (worker) | Proceso interno | — | Tareas automáticas que la maqueta simula: CAE de ARCA y envío de mails. | — |
| Afiliado | Externo, no ingresa | — | Recibe facturas, recibos, resúmenes, promociones y saludos por mail o WhatsApp. | — |
| Interesado | Externo, no ingresa | — | Persona que pregunta por los planes; recibe cotizaciones e información. | — |
| ARCA | Sistema externo | — | Autoriza las facturas de cuota y devuelve el CAE. | — |
| Proveedor de mail | Sistema externo | — | Entrega los mails desde sistema@integraldeleste.com.ar. | — |

**Notas sobre los roles**

* El usuario CMDE dejó de ser unidad en la v78: hoy es un usuario de atención que emite autorizaciones para las demás unidades.  
* Las unidades aparecen en la lista de usuarios recordados del ingreso; los comercios no, escriben su usuario.  
* Un usuario de atención sin secciones habilitadas ve la pantalla "Sin secciones asignadas".  
* La maqueta define el permiso "Modificar datos de afiliados" para usuarios, pero ningún usuario de atención llega a la ficha del afiliado (ver P-02).

## **2\. Índice de casos de uso**

| ID | Caso de uso | Actor principal | Módulo |
| :---- | :---- | :---- | :---- |
| CU-01 | Ingresar al sistema | Todos los roles | Acceso |
| CU-02 | Cerrar sesión (manual o por inactividad) | Todos los roles | Acceso |
| CU-03 | Cambiar la clave propia | Todos los roles | Acceso |
| CU-04 | Autorizar una acción restringida con clave de administrador | Usuario \+ Administrador | Acceso |
| CU-05 | Buscar en el padrón de afiliados | Administrador | Afiliados |
| CU-06 | Dar de alta un grupo de afiliados | Usuario, Administrador | Afiliados |
| CU-07 | Consultar la ficha del afiliado | Administrador | Afiliados |
| CU-08 | Modificar datos del afiliado y del grupo | Administrador | Afiliados |
| CU-09 | Gestionar la promoción en la cuota de un grupo | Administrador | Afiliados |
| CU-10 | Dar de baja un afiliado con propuestas de retención | Administrador | Afiliados |
| CU-11 | Reactivar un afiliado | Administrador | Afiliados |
| CU-12 | Atender a un afiliado que quiere darse de baja | Usuario, Administrador | Afiliados |
| CU-13 | Resolver casos de retención pendientes | Administrador | Afiliados |
| CU-14 | Saludar a los afiliados que cumplen años | Usuario, Administrador | Afiliados |
| CU-15 | Cotizar un plan | Usuario, Administrador | Comercial |
| CU-16 | Consultar los beneficios de los planes | Usuario, Administrador | Comercial |
| CU-17 | Enviar información a una persona | Usuario, Administrador | Comercial |
| CU-18 | Armar promociones | Administrador | Comercial |
| CU-19 | Enviar una promoción a los afiliados | Administrador | Comercial |
| CU-20 | Verificar y autorizar una prestación | Usuario, Administrador | Beneficios |
| CU-21 | Auditar solicitudes de autorización | Administrador | Beneficios |
| CU-22 | Verificar afiliado y registrar uso en una unidad | Unidad de negocio | Beneficios |
| CU-23 | Verificar afiliado y registrar consumo en un comercio | Comercio adherido | Beneficios |
| CU-24 | Consultar los usos en unidades | Administrador | Beneficios |
| CU-25 | Consultar el historial de beneficios de un afiliado | Usuario, Administrador | Beneficios |
| CU-26 | Facturar las cuotas del mes | Administrador | Caja |
| CU-27 | Obtener el CAE de ARCA | Sistema | Caja |
| CU-28 | Enviar las facturas del mes por mail | Administrador, Sistema | Caja |
| CU-29 | Registrar cobros por débito automático | Administrador | Caja |
| CU-30 | Cobrar cuotas en mostrador | Administrador | Caja |
| CU-31 | Ver, imprimir y enviar una factura o un recibo | Administrador | Caja |
| CU-32 | Consultar la cuenta corriente de un grupo | Administrador | Caja |
| CU-33 | Registrar egresos | Administrador | Gestión |
| CU-34 | Consultar la contabilidad | Administrador | Gestión |
| CU-35 | Consultar estadísticas | Administrador | Gestión |
| CU-36 | Enviar el resumen mensual de beneficios | Administrador | Gestión |
| CU-37 | Enviar el resumen de ahorro a un titular | Administrador | Gestión |
| CU-38 | Descargar informes | Administrador | Gestión |
| CU-39 | Gestionar usuarios | Administrador | Configuración |
| CU-40 | Gestionar prestaciones y valores de referencia | Administrador | Configuración |
| CU-41 | Importar precios desde PDF o Excel | Administrador | Configuración |
| CU-42 | Configurar reglas de beneficios por plan | Administrador | Configuración |
| CU-43 | Gestionar unidades de negocio | Administrador | Configuración |
| CU-44 | Configurar beneficios de comercios adheridos | Administrador | Configuración |
| CU-45 | Configurar precios por plan y categoría | Administrador | Configuración |
| CU-46 | Configurar parámetros de facturación | Administrador | Configuración |
| CU-47 | Configurar qué acciones requieren administrador | Administrador | Configuración |
| CU-48 | Editar los textos de los mails | Administrador | Configuración |
| CU-49 | Confirmar y deshacer cambios de configuración | Administrador | Configuración |
| CU-50 | Gestionar copias de seguridad | Administrador | Configuración |
| CU-51 | Volver a cargar el padrón inicial | Administrador | Configuración |
| CU-52 | Consultar historial de cambios y versiones | Administrador (versiones: todos) | Configuración |

### **Reglas transversales**

Los casos de uso las citan por su código (RT-xx) en lugar de repetirlas.

* **RT-01 Búsqueda de afiliado:** acepta apellido y nombre (con sugerencias desde la primera letra, hasta 8), DNI (con o sin puntos; desde 6 dígitos), CUIT de 11 dígitos (se compara su DNI interno) o N° de afiliado. Las sugerencias muestran primero los activos y marcan "baja".  
* **RT-02 Formatos:** nombres y apellidos en mayúsculas; DNI con separador de miles (28.913.458); CUIT con guiones (20-34014173-4); montos en pesos con separador de miles y coma decimal; fechas dd/mm/aaaa. La búsqueda ignora tildes y mayúsculas.  
* **RT-03 Celular para WhatsApp:** se normaliza a 10 dígitos con característica, sin 0 ni 15, y se envía como 549 \+ número. Es válido solo si queda 549 seguido de 10 dígitos.  
* **RT-04 Envío por WhatsApp:** se arma un PDF con el logo y los datos y un mensaje corto. En el celular se comparte con el PDF adjunto; en la computadora se descarga y se abre wa.me para adjuntarlo. Sin celular, WhatsApp deja elegir el contacto.  
* **RT-05 Cuota al día:** un grupo tiene deuda vencida si alguna factura está pendiente y su vencimiento es anterior a hoy. Estados de cuenta: "Deuda vencida", "Cuota por vencer" (saldo \> 0 sin vencidas) y "Al día".  
* **RT-06 Cálculo del beneficio** (por plan del grupo y prestación):  
  * Solo aplica si el afiliado está activo y su grupo cumple RT-05 sin deuda vencida.  
  * Antigüedad \= días desde la fecha de alta del afiliado.  
  * Sin cargo: si la regla tiene gratis \> 0 y la antigüedad alcanza carGratis, quedan gratis menos los usados en la ventana (mes calendario, semestre ene–jun o jul–dic, año calendario), sumando a todo el grupo si porGrupo.  
  * Porcentaje: si hay escala (días:porcentaje), rige el último escalón alcanzado; si no, desc cuando la antigüedad alcanza car.  
  * maxDesc: si ya usó esa cantidad con descuento en el año calendario, el porcentaje es 0\.  
  * Modo hasta: el porcentaje es un máximo; en la maqueta la unidad registra siempre el máximo (P-07).  
  * Ahorro \= valor × cantidad sin cargo \+ valor × cantidad con descuento × porcentaje; el valor es el precio que informa la unidad o, si no lo informa, el valor de referencia de la prestación.  
* **RT-07 Historial de cambios:** toda alta, baja, modificación, cambio de configuración, envío masivo y sesión queda registrada con fecha y hora, acción, detalle (antes → después), usuario y, si corresponde, el administrador que autorizó.  
* **RT-08 Datos personales:** el padrón solo lo ven los administradores. Usuarios, unidades y comercios encuentran al afiliado por un dato puntual; las unidades y comercios ven nombre, N° y la inicial del apellido en sus listas del día.  
* **RT-09 Esc:** cancela el modal o la edición abierta; para usuarios de atención, vuelve a Inicio.  
* **RT-10 Comprobantes para el afiliado:** facturas y recibos que recibe el afiliado no muestran quién cobró ni quién emitió; ese dato queda solo para uso interno (v72).

## **3\. Acceso y sesión**

### **CU-01 · Ingresar al sistema**

**Actor:** todos los roles · **Pantalla:** Ingreso

**Precondiciones:** el usuario existe y no hay sesión abierta en ese navegador.

**Flujo principal**

1. El sistema muestra usuario y clave, y una pestaña con los usuarios que ya ingresaron desde ese equipo (el último primero).  
2. El actor escribe o elige su usuario y escribe la clave (con botón para verla).  
3. El sistema valida el usuario sin distinguir mayúsculas ni tildes, y la clave.  
4. El sistema recuerda el usuario en ese equipo, abre la sesión y lleva a la pantalla de inicio de su rol (ver sección 1).  
5. Si es administrador y hay solicitudes en auditoría, muestra un aviso con la cantidad y el botón "Ir a auditoría".

**Flujos alternativos y de error**

* A1 · Olvidar un usuario: con la × de la lista se quita ese usuario de los recordados del equipo.  
* E1 · Usuario inexistente: "No existe el usuario … Revisá cómo está escrito."  
* E2 · Clave incorrecta: indica que la clave no es correcta y que otro administrador puede ponerle una nueva.  
* E3 · Usuario dado de baja: "Este usuario está dado de baja. Hablá con un administrador."

**Reglas de negocio**

* La lista de recordados guarda hasta 15 usuarios por equipo y muestra las unidades (marcadas "unidad"), no los comercios.  
* El enlace "Demo: volver todas las claves a su nombre" es solo de la maqueta y no va en el sistema (SUPUESTO S-01).  
* Límite de intentos fallidos: ver documento de Arquitectura, sección 6\.

**Datos:** usuario (nombre, hash de clave, rol, activo, secciones), usuarios recordados por equipo, sesión.

**Criterios de aceptación**

* ☐ "KATY", "katy" y "Káty" ingresan como el mismo usuario.  
* ☐ Un comercio que ingresó no aparece en la lista de recordados; una unidad sí.  
* ☐ Un usuario de atención llega a Inicio; un administrador, a Afiliados; una unidad o comercio, a Verificar afiliado.  
* ☐ Con 2 solicitudes pendientes, un administrador ve el aviso con "2 solicitudes" al ingresar; un usuario no lo ve.  
* ☐ Un usuario dado de baja no puede ingresar aunque la clave sea correcta.

### **CU-02 · Cerrar sesión**

**Actor:** todos los roles · **Pantalla:** barra superior (todas)

**Precondiciones:** sesión abierta.

**Flujo principal**

1. El actor toca "Salir" (arriba a la derecha, junto a su nombre, o en el recuadro del usuario del menú).  
2. El sistema registra la salida en el historial y vuelve a la pantalla de ingreso, descartando lo que estaba a medio cargar.

**Flujos alternativos**

* A1 · Inactividad: si pasan 15 minutos sin clics, teclas, scroll ni toques, el sistema cierra la sesión, lo registra como "cerrada por inactividad" y muestra "Se cerró la sesión por inactividad. Volvé a ingresar."

**Reglas de negocio:** el límite de inactividad es de 15 minutos y lo controla el servidor (Arquitectura, sección 6). Al abrir el sistema siempre se pide usuario y clave.

**Datos:** sesión, historial de cambios.

**Criterios de aceptación**

* ☐ Tras 15 minutos sin actividad, cualquier pedido a la API devuelve "sesión vencida" y la pantalla vuelve al ingreso con el mensaje.  
* ☐ Una alta a medio cargar no queda guardada después de salir.  
* ☐ El historial muestra "… salió del sistema" o "Sesión de … cerrada por inactividad".

### **CU-03 · Cambiar la clave propia**

**Actor:** todos los roles · **Pantalla:** "Cambiar mi clave" (barra superior o Configuración → Mi cuenta)

**Precondiciones:** sesión abierta.

**Flujo principal**

1. El actor escribe la clave actual, la nueva y la repite.  
2. El sistema valida y guarda la clave nueva.  
3. Registra "… cambió su clave" y confirma "Clave cambiada."

**Errores:** E1 clave actual incorrecta; E2 las claves nuevas no coinciden; E3 menos de 4 caracteres.

**Reglas de negocio:** mínimo 4 caracteres, como en la maqueta (P-03 sobre la política de claves).

**Datos:** usuario (hash de clave), historial.

**Criterios de aceptación**

* ☐ Con la clave actual mal escrita no se cambia nada.  
* ☐ Tras cambiarla, la clave anterior deja de servir para ingresar.

### **CU-04 · Autorizar una acción restringida con clave de administrador**

**Actor:** usuario de atención (pide) \+ administrador (autoriza) · **Pantalla:** modal "Requiere un administrador"

**Precondiciones:** la acción está marcada en Configuración → Qué requiere administrador (CU-47) y quien la intenta no es administrador.

**Flujo principal**

1. El usuario intenta la acción; el sistema muestra cuál es y pide usuario y clave de un administrador.  
2. Un administrador escribe sus datos en ese mismo equipo.  
3. El sistema valida que sea un administrador activo con esa clave y ejecuta la acción.  
4. El historial registra la acción a nombre del usuario y "autorizó …".

**Errores:** E1 usuario o clave incorrectos, o el usuario no es administrador: no se ejecuta nada.

**Reglas de negocio**

* Acciones configurables: modificar datos de afiliados, aplicar un descuento adicional en un recibo de prestaciones, modificar montos (precios y cuotas), volver a cargar el padrón.  
* Si quien actúa ya es administrador, no se pide autorización.  
* La autorización vale solo para esa acción; no deja al usuario con permisos de administrador.

**Datos:** permisos configurados, usuario autorizante, historial.

**Criterios de aceptación**

* ☐ Con "Modificar montos" activado, un usuario no puede guardar un cambio de cuota sin la clave de un administrador.  
* ☐ La API rechaza la acción si el token de autorización ya se usó o es de otra acción.  
* ☐ El historial muestra el usuario y "(autorizó Katy)".

## **4\. Afiliados**

### **CU-05 · Buscar en el padrón de afiliados**

**Actor:** administrador · **Pantalla:** Afiliados

**Precondiciones:** sesión de administrador.

**Flujo principal**

1. El sistema muestra cuántos afiliados activos hay en total y por plan, y la tabla de activos ordenada por N°.  
2. El actor escribe en el buscador y elige el estado (Activos, Bajas, Todos).  
3. El sistema filtra en el momento y muestra N°, nombre (y "Paga: titular" si no es titular), CUIT/DNI, categoría y plan, cuota, medio de pago, alta y estado de cuenta (RT-05) o "Baja".  
4. Al tocar una fila (o Enter), abre la ficha (CU-07).

**Alternativos:** A1 · "Nueva alta" lleva a CU-06. A2 · Sin resultados: "No hay afiliados que coincidan."

**Reglas de negocio:** el texto busca todas las palabras dentro de nombre \+ N°; desde 4 dígitos también busca dentro del documento.

**Datos:** afiliados, grupos, facturas (para el estado de cuenta).

**Criterios de aceptación**

* ☐ "ramallo 75" encuentra al afiliado N° 75 de apellido Ramallo.  
* ☐ "2891" encuentra al DNI 28.913.458.  
* ☐ Un usuario de atención recibe 403 al pedir el padrón a la API.

### **CU-06 · Dar de alta un grupo de afiliados**

**Actor:** usuario de atención, administrador · **Pantalla:** Altas (o "Nueva alta" desde Afiliados; "Iniciar alta" desde el Cotizador)

**Precondiciones:** el plan tiene precio para todas las categorías que se van a cargar.

**Flujo principal**

1. El actor carga al titular (quien paga): apellido, primer y segundo nombre, DNI o CUIT, fecha de nacimiento, plan, categoría, teléfono, email, forma de pago, fecha de alta y observaciones.  
2. Si corresponde, aplica una promoción (porcentaje y meses).  
3. Agrega familiares: el apellido se copia del titular; carga nombres, documento, nacimiento y categoría.  
4. El sistema muestra la cuota mensual estimada del grupo (con y sin promoción).  
5. El actor toca "Confirmar alta". El sistema valida, crea el grupo y los afiliados con N° correlativos y registra la promoción en el historial.  
6. Confirma "Alta registrada: …, afiliado N° …". El administrador vuelve al padrón filtrado por ese N°; el usuario, a Inicio.

**Flujos alternativos y de error**

* A1 · Sin email: con "No tiene email" el campo deja de ser obligatorio.  
* A2 · Plan individual (Salud, Sepelio): no deja agregar familiares.  
* A3 · Cancelar (o Esc con datos cargados): descarta el alta.  
* E1 · DNI del titular ya activo: al terminar de escribirlo avisa "… ya está en el padrón (afiliado N° …)" y sale del alta.  
* E2 · DNI de un familiar ya activo: marca el campo "Ya está afiliado".  
* E3 · Dos integrantes con el mismo documento: no confirma.  
* E4 · Plan individual con familiares: no confirma.  
* E5 · Categoría sin precio en el plan: "todavía no tiene precio para todas las categorías".

**Reglas de negocio**

* Categorías del titular: Titular o Titular \+64 años. De los adicionales: menor de 18, 18 a 49, 50 a 64 y \+64 años.  
* Todo el grupo tiene la misma forma de pago; el email es del grupo y es obligatorio salvo A1.  
* La cuota de cada afiliado es el precio de su categoría en el plan al momento del alta y queda guardada en el afiliado.  
* Promoción: 5, 10, 15, 20, 25, 30, 35 o 40 % durante 1, 2, 3 o 6 meses, desde el primer mes facturado (el mayor entre el mes de alta y noviembre 2026).  
* La comparación de documentos usa el DNI interno del CUIT (RT-01) y solo contra afiliados activos (P-08).

**Datos:** grupo (plan, forma de pago, email, fecha de alta, promoción), afiliados (N°, nombre por partes, documento, nacimiento, categoría, cuota, teléfono, observaciones).

**Criterios de aceptación**

* ☐ Un titular Plus \+ cónyuge de 30 años \+ hijo de 10 años estima \$ 25.000 \+ \$ 20.000 \+ \$ 18.750 \= \$ 63.750 con los precios vigentes.  
* ☐ Con 20 % por 3 meses, muestra \$ 51.000 "los primeros 3 meses, después \$ 63.750".  
* ☐ No se puede confirmar un Plan Salud con un familiar.  
* ☐ Dos altas confirmadas al mismo tiempo reciben N° distintos y consecutivos.

### **CU-07 · Consultar la ficha del afiliado**

**Actor:** administrador · **Pantalla:** panel lateral "Ficha" (desde Afiliados)

**Precondiciones:** el afiliado existe.

**Flujo principal**

1. El sistema muestra N°, nombre, estado de cuenta o "Baja", y los datos: documento, plan, categoría, cuota, nacimiento con edad (y "¡Hoy cumple años\!"), alta, teléfono, quién paga y con qué, datos de facturación, email, observaciones y fecha de baja.  
2. Muestra los consumos en comercios (últimos 15), los beneficios usados en el año (CU-25) y el grupo de pago: cuota del grupo, saldo, promoción, email e integrantes.  
3. El actor puede: editar (CU-08), gestionar la promoción (CU-09), ver la cuenta corriente (CU-32), dar de baja (CU-10) o reactivar (CU-11), y abrir la ficha de otro integrante.

**Datos:** afiliado, grupo, facturas, recibos, usos en unidades y comercios.

**Criterios de aceptación**

* ☐ Al tocar un integrante del grupo se abre su ficha.  
* ☐ La cuota del grupo coincide con la suma de las cuotas de los activos, con la promoción vigente aplicada.

### **CU-08 · Modificar datos del afiliado y del grupo**

**Actor:** administrador (o usuario con autorización, CU-04) · **Pantalla:** Ficha → "Editar datos"

**Precondiciones:** permiso "Modificar datos de afiliados" (CU-47).

**Flujo principal**

1. El actor edita apellido, nombres, documento, nacimiento, categoría, cuota, teléfono, fecha de alta y observaciones.  
2. Si es el titular, también edita plan, forma de pago y email del grupo, y para quién se factura: condición frente al IVA, CUIT y razón social.  
3. Al cambiar la categoría o el plan, el sistema propone la cuota del nuevo precio.  
4. El actor guarda; el sistema registra cada campo como "antes → después".

**Alternativos y errores:** A1 · Cancelar o Esc deja todo como estaba. E1 · Si cambia la cuota o el plan y no es administrador, se pide autorización (CU-04, permiso "Modificar montos").

**Reglas de negocio**

* Al cambiar el plan del grupo, se actualiza la cuota de los otros integrantes cuya cuota era igual al precio anterior de su categoría.  
* Condición Responsable Inscripto → Factura A; el resto → Factura B (CU-26).

**Datos:** afiliado, grupo (plan, forma de pago, email, datos fiscales), historial.

**Criterios de aceptación**

* ☐ Cambiar el teléfono registra "teléfono: … → …" con el usuario.  
* ☐ Pasar el grupo a Responsable Inscripto hace que la próxima factura sea A con el CUIT cargado.

### **CU-09 · Gestionar la promoción en la cuota de un grupo**

**Actor:** administrador · **Pantalla:** Ficha → "Agregar promoción" o "Renovar, extender o quitar promoción"

**Precondiciones:** grupo existente.

**Flujo principal**

1. El sistema muestra la promoción actual (y si ya terminó).  
2. El actor elige porcentaje (5 a 40 %), duración (1, 2, 3 o 6 meses) y mes de inicio.  
3. El sistema muestra cuánto pagará y en qué meses; el actor toca "Aplicar".  
4. Registra "antes → después" en el historial.

**Alternativos:** A1 · "Quitar promoción". E1 · Sin permiso de montos se pide autorización (CU-04).

**Reglas de negocio:** el mes de inicio propuesto es el siguiente al fin de la promoción vigente o, si no hay, el mes que viene; nunca antes de noviembre 2026\. Un grupo tiene una sola promoción a la vez.

**Datos:** grupo.promo (porcentaje, meses, desde, hasta, origen retención).

**Criterios de aceptación**

* ☐ Con promoción hasta enero 2027, la propuesta de inicio es febrero 2027\.  
* ☐ La factura de un mes dentro de la promoción muestra la bonificación y "Mes N de M".

### **CU-10 · Dar de baja un afiliado con propuestas de retención**

**Actor:** administrador · **Pantalla:** Ficha → "Dar de baja" (modal "Antes de dar de baja a …")

**Precondiciones:** afiliado activo; solo administradores dan de baja.

**Flujo principal**

1. El sistema muestra antigüedad, plan, cuánto ahorró y qué le queda sin usar.  
2. El actor elige el motivo y anota lo que dijo el afiliado.  
3. El sistema muestra las propuestas en orden (tabla de CU-12); solo se habilita la siguiente si la anterior no le interesó.  
4. Si acepta una, se aplica (CU-12, regla de propuestas), se registra como "retenido" y no hay baja.  
5. Si rechaza todas, "Dar de baja igual" pasa al afiliado a baja con fecha de hoy y registra la retención como "baja".

**Alternativos:** A1 · Motivo "Fallecimiento": no se ofrece nada y se puede dar de baja directamente.

**Reglas de negocio:** la baja es por afiliado: desde ese día deja de sumar a la cuota del grupo. No se puede dar de baja sin elegir motivo.

**Datos:** afiliado (estado, fecha de baja), retenciones (motivo, comentario, propuestas y respuestas, resultado, usuario).

**Criterios de aceptación**

* ☐ "Dar de baja igual" no aparece hasta que se rechazó la última propuesta (salvo fallecimiento).  
* ☐ La factura del mes siguiente a la baja no incluye a ese afiliado.  
* ☐ Un usuario de atención recibe 403 al pedir la baja a la API.

### **CU-11 · Reactivar un afiliado**

**Actor:** administrador · **Pantalla:** Ficha de un afiliado dado de baja → "Reactivar"

**Flujo principal:** 1\. El actor toca "Reactivar". 2\. El sistema lo pasa a activo, borra la fecha de baja y registra "Reactivación".

**Reglas de negocio:** conserva el N° y la fecha de alta original (P-08 sobre la carencia al volver).

**Criterios de aceptación**

* ☐ Después de reactivarlo vuelve a sumar a la cuota del grupo en la próxima facturación.

### **CU-12 · Atender a un afiliado que quiere darse de baja**

**Actor:** usuario de atención, administrador · **Pantalla:** Quiere darse de baja

**Precondiciones:** sección habilitada para el usuario.

**Flujo principal**

1. **Quién es:** el actor busca al afiliado (RT-01).  
2. **Lo que hay que saber:** el sistema muestra antigüedad, cuota e integrantes, ahorro total y del año, lo que usó este año, lo que tiene sin usar, el grupo con edades, cumpleaños, promociones vigentes para su plan y pedidos de baja anteriores. Botón para mandarle por WhatsApp todo lo que tiene (RT-04).  
3. **Por qué se quiere ir:** el actor elige el motivo y anota lo que dijo.  
4. **Qué decirle:** el sistema muestra un guión por motivo (frase de apertura, argumentos con sus datos y qué evitar).  
5. **Qué ofrecerle:** las propuestas, de a una y en orden.  
6. **Cómo terminó:** "Aceptó, se queda", "Lo va a pensar" o "Insiste en darse de baja".

**Flujos alternativos**

* A1 · La propuesta la decide administración (cambio de plan, descuentos, llamado): el usuario toca "Pasar a administración" y queda registrado como "derivado".  
* A2 · "Lo va a pensar": se registra con fecha de llamado a 3 días; ese día aparece en "Para llamar hoy" al entrar a la pantalla.  
* A3 · "Insiste": el usuario registra "baja pedida" y la confirma un administrador (CU-13); un administrador puede darlo de baja en el momento.  
* E1 · Afiliado ya dado de baja: "Este afiliado ya está dado de baja."

**Propuestas por motivo** (solo se muestran las disponibles):

| Motivo | Orden de las propuestas |
| :---- | :---- |
| Problemas económicos / la cuota es cara | Recordarle lo que tiene → Pasar al plan inferior → 10 % por 3 meses → 20 % por 3 meses |
| No lo usa | Recordarle lo que tiene → Beneficio de regalo → 10 % por 3 meses |
| Tiene otra cobertura | Recordarle lo que tiene → Beneficio de regalo → 10 % por 3 meses |
| Se muda de la zona | Pasar al plan inferior → Recordarle lo que tiene |
| Tuvo una mala experiencia | Llamado de un administrador → Beneficio de regalo → 10 % → 20 % |
| Otro motivo | Recordarle lo que tiene → Beneficio de regalo → 10 % → 20 % |
| Fallecimiento | Ninguna: solo acompañar |

**Reglas de negocio**

* Plan inferior: Plus → Integral, Integral → Salud; Salud y Sepelio no tienen. Solo se ofrece si ese plan tiene precio (P-09).  
* 20 % solo si lleva 180 días o más y está al día (RT-05).  
* Si el grupo fue retenido en los últimos 365 días, no se ofrecen descuentos en la cuota.  
* El usuario de atención solo puede cerrar "Recordarle lo que tiene" y "Beneficio de regalo"; el resto lo deriva.  
* Al aceptar un descuento: promoción en el grupo desde el mes siguiente, por 3 meses, marcada como de retención. Al aceptar el cambio de plan: cambia el plan y la cuota de cada integrante al precio del nuevo plan.  
* El "beneficio de regalo" se coordina a mano con la unidad (P-10).  
* Cada registro nuevo cierra los casos pendientes anteriores del mismo afiliado.

**Datos:** retenciones (fecha, afiliado, grupo, motivo, comentario, propuestas con respuesta, resultado: retenido, derivado, piensa, baja pedida o baja; propuesta aceptada; fecha de llamado; usuario).

**Criterios de aceptación**

* ☐ Con motivo "económico" y 4 meses de antigüedad, la propuesta de 20 % no aparece.  
* ☐ Un usuario no puede aplicar el 10 %: solo ve "Pasar a administración".  
* ☐ "Lo va a pensar" un lunes hace aparecer al afiliado en "Para llamar hoy" el jueves.

### **CU-13 · Resolver casos de retención pendientes**

**Actor:** administrador · **Pantalla:** Estadísticas → Retención de afiliados → "Para resolver"

**Flujo principal**

1. El sistema lista los casos abiertos derivados, con baja pedida o "lo piensa", con motivo, comentario, quién atendió y cuándo.  
2. "Atender" abre CU-12 con el afiliado y el motivo ya cargados.  
3. En "baja pedida", "Confirmar baja" da de baja al afiliado y cierra el caso.

**Reglas de negocio:** el panel también muestra pedidos de baja, cuántos se quedaron (y el porcentaje), cuántos se fueron, cuántos esperan, los motivos y qué propuesta los convenció.

**Criterios de aceptación**

* ☐ Un caso confirmado desaparece de "Para resolver" y suma en "Se fueron".

### **CU-14 · Saludar a los afiliados que cumplen años**

**Actor:** usuario de atención, administrador · **Pantalla:** franja de cumpleaños (todas las pantallas internas)

**Flujo principal**

1. El sistema muestra "Hoy cumple(n) años: …" con nombre y edad de los afiliados activos que cumplen hoy y todavía no fueron saludados este año (hasta 4 con botón).  
2. El actor toca "Saludar a …": se abre WhatsApp con un saludo y un PDF de cumpleaños (RT-04).  
3. El sistema lo marca como saludado ese año y lo saca de la franja.

**Datos:** afiliado (fecha de nacimiento, teléfono), saludos por año y afiliado.

**Criterios de aceptación**

* ☐ Un afiliado saludado no vuelve a aparecer ese día para ningún usuario.  
* ☐ Los afiliados dados de baja no aparecen.

## **5\. Comercial**

### **CU-15 · Cotizar un plan**

**Actor:** usuario de atención, administrador · **Pantalla:** Cotizador

**Precondiciones:** sección habilitada.

**Flujo principal**

1. El actor elige el plan a cotizar (Plus, Integral, Salud o Sepelio) desde la barra o tocando su tarjeta.  
2. Arma la lista de integrantes eligiendo la categoría de cada uno (arranca con Titular, adicional 18 a 49 y adicional menor de 18).  
3. El sistema muestra, para cada plan, la cuota mensual con el detalle por integrante, y si hay promoción, la cuota con descuento y la de después.  
4. El actor escribe "Apellido y nombre del interesado" y el celular, y toca "Enviar cotización en PDF".  
5. El sistema registra una consulta (canal WhatsApp, "Cotización") y abre el envío por WhatsApp con el PDF de la cotización y un saludo por el primer nombre (RT-04).

**Flujos alternativos y de error**

* A1 · "Promoción": elige 5 a 40 % y 1, 2, 3 o 6 meses; se ve en rojo arriba a la derecha.  
* A2 · "Iniciar alta con este plan": abre CU-06 con el plan, las categorías y la promoción ya cargadas.  
* A3 · "Enviar este plan por WhatsApp" en cada bloque de beneficios: manda el PDF del plan con la cuota del grupo cotizado.  
* E1 · Falta el nombre o el celular no es válido (RT-03): marca el campo en rojo y explica qué falta.

**Reglas de negocio**

* Plan individual: solo cotiza al primer integrante y avisa "Plan individual: solo cubre al titular".  
* Si una categoría no tiene precio en un plan, ese plan muestra "Precio a definir".  
* El nombre del interesado se pasa a mayúsculas y el sistema separa apellido y nombres aunque se escriban al revés, usando una lista de nombres frecuentes y los nombres del padrón.  
* Siempre queda al menos un integrante.  
* Debajo se muestran los beneficios de cada plan con su carencia y la tabla de precios vigentes por categoría.

**Datos:** precios por plan y categoría, beneficios por plan, consultas (fecha, hora, usuario, canal, nombre, celular, planes, cómo).

**Criterios de aceptación**

* ☐ "MARÍA JOSÉ GÓMEZ" se muestra como Apellido: GÓMEZ · Nombre: MARÍA JOSÉ y el saludo dice "Hola María".  
* ☐ Con celular "03435 15 013651" el envío usa 5493435013651\.  
* ☐ Cada cotización enviada aparece en Estadísticas → Consultas atendidas.

### **CU-16 · Consultar los beneficios de los planes**

**Actor:** usuario de atención, administrador · **Pantalla:** Beneficios

**Flujo principal**

1. El sistema muestra la tabla de beneficios por plan (Plus, Integral, Salud, Sepelio) con el texto y la carencia de cada uno, y las condiciones generales.  
2. El actor busca por palabras (por ejemplo "óptica", "sesiones", "30%") y filtra por plan.  
3. El sistema muestra solo los beneficios que contienen todas las palabras; los que no incluye un plan dicen "No incluido".

**Alternativos:** A1 · "Enviar … por WhatsApp" por cada plan (RT-04). A2 · Sin coincidencias: "No hay beneficios que coincidan con …".

**Reglas de negocio:** los textos son los del PDF "Beneficios detallados por planes principales"; un beneficio marcado "igual que el Integral" muestra el mismo texto. Condiciones: descuentos de adicionales por edad, \$ 4.000 adicionales desde los 64 años, planes individuales y edad máxima de adhesión al sepelio (74 años, 76 con certificado).

**Datos:** catálogo de beneficios por plan (rubro, texto, carencia), condiciones.

**Criterios de aceptación**

* ☐ Buscar "hiperbárica" muestra 1 sesión sin costo en Integral y 5 en Plus.  
* ☐ Filtrar por Plan Salud oculta las otras columnas.

### **CU-17 · Enviar información a una persona**

**Actor:** usuario de atención, administrador · **Pantalla:** Beneficios → "Enviar información a una persona"

**Flujo principal**

1. El actor elige a quién: "A alguien que pregunta por los planes" o "A un afiliado".  
2. Interesado: escribe apellido y nombre y marca los planes que le interesan. Afiliado: lo busca (RT-01) y el sistema completa el celular desde su ficha o la del titular.  
3. Escribe o confirma el celular y toca "Enviar PDF por WhatsApp".  
4. El sistema arma el PDF (interesado: cuotas y beneficios de los planes elegidos; afiliado: beneficios disponibles, lo usado y el ahorro del año), abre el envío (RT-04) y registra la consulta.

**Alternativos y errores:** A1 · "Ver PDF" lo descarga sin registrar. E1 · Falta el afiliado, el nombre, un plan o un celular válido: avisa qué falta y marca el campo.

**Reglas de negocio:** desde acá solo se informa; autorizaciones, excepciones y descuentos los decide administración (el sistema lo aclara a los usuarios). No se manda texto suelto: siempre PDF (v80).

**Datos:** consultas (tipo, afiliado o nombre, celular, planes, cómo \= "PDF").

**Criterios de aceptación**

* ☐ Elegir un afiliado sin celular cargado deja el campo vacío y no permite compartir hasta completarlo.  
* ☐ El PDF de un afiliado muestra "Ya disponible" o "Disponible desde el …" según la carencia de cada beneficio.

### **CU-18 · Armar promociones**

**Actor:** administrador · **Pantalla:** Promociones

**Flujo principal**

1. El actor completa título, beneficio (lo que se ve grande en el PDF), dónde se usa (Integral en general o una unidad activa), desde y hasta, descripción, condiciones y a quién va (todos o ciertos planes).  
2. Toca "Ver cómo queda el PDF" para revisarlo.  
3. Toca "Guardar promoción"; el sistema la agrega a "Promociones armadas" y la registra.

**Alternativos y errores**

* A1 · Editar: carga la promoción en el formulario; "Guardar cambios" o "Cancelar".  
* A2 · Eliminar: pide confirmación y aclara que los envíos ya hechos no se deshacen.  
* E1 · Faltan título, beneficio o fechas, o "hasta" es anterior a "desde": marca los campos.

**Reglas de negocio**

* Estado: Programada (antes de "desde"), Vigente o Terminada (después de "hasta").  
* Una promoción vigente de una unidad se le muestra a esa unidad al verificar al afiliado (CU-22).  
* Cada tarjeta muestra a cuántos destinatarios ya se envió sobre el total.

**Datos:** promociones (título, beneficio, unidad, desde, hasta, texto, condiciones, planes, creada, por, envíos por grupo).

**Criterios de aceptación**

* ☐ Una promoción con "hasta" ayer muestra "Terminada" y su botón "Enviar" está deshabilitado.  
* ☐ La Óptica ve la promoción vigente de Óptica al verificar a un afiliado al día.

### **CU-19 · Enviar una promoción a los afiliados**

**Actor:** administrador (y Sistema para el mail) · **Pantalla:** Promociones → "Enviar a los afiliados"

**Precondiciones:** promoción no terminada.

**Flujo principal**

1. El sistema muestra destinatarios (titulares de grupos activos de los planes elegidos), cuántos ya la recibieron y cuántos faltan (con email).  
2. **Por mail, a todos juntos:** muestra un ejemplo del mail con el nombre del primero y el PDF personalizado; al confirmar, se envían a los que tienen email y no la recibieron, y quedan marcados "Mail".  
3. **Por WhatsApp, de a uno:** "Enviar al siguiente: …" arma el PDF con su nombre, abre WhatsApp con su número y lo marca "WhatsApp"; se repite con el siguiente.  
4. La tabla de afiliados muestra contacto y estado de envío de cada uno, con un botón WhatsApp individual.

**Alternativos:** A1 · Nadie con celular válido entre los que faltan: "A los que faltan no les tenemos un celular cargado." A2 · Todos recibidos: "¡Listo\! Ya se la mandaste a todos."

**Reglas de negocio:** el mail usa la plantilla "Promociones" (CU-48) con las variables {nombre}, {titulo}, {beneficio}, {lugar}, {texto}, {desde} y {hasta}. Un grupo no recibe dos veces la misma promoción por el envío masivo. El celular se toma del titular o del primer integrante que lo tenga.

**Datos:** promociones.envíos (grupo → fecha y canal), cola de mails.

**Criterios de aceptación**

* ☐ Repetir el envío masivo no duplica mails a quienes ya la recibieron.  
* ☐ Cada mail lleva el PDF con el nombre de ese afiliado.  
* ☐ El historial registra "Promoción … enviada por mail a N".

## **6\. Beneficios**

### **CU-20 · Verificar y autorizar una prestación**

**Actor:** usuario de atención, administrador · **Pantalla:** Autorizaciones

**Precondiciones:** sección habilitada; existe al menos una prestación activa.

**Flujo principal**

1. El actor identifica al afiliado: el usuario lo busca por nombre, DNI, CUIT o N° (RT-01, sin ver el padrón); el administrador puede elegirlo de una lista.  
2. Elige la prestación o beneficio.  
3. El sistema muestra la verificación: afiliado activo, carencia, beneficio del plan, pagos al día; y la tabla de beneficios usados y disponibles del año.  
4. Si todo da bien, dice "Corresponde autorizar" y el actor toca "Emitir autorización".  
5. El sistema crea la autorización con N° correlativo, descuenta el beneficio (crea un uso pendiente de confirmar por la unidad) e informa "Autorización N° … emitida · se descontó del beneficio (sin cargo / N %)".

**Flujos alternativos y de error**

* A1 · No corresponde: muestra el motivo ("En carencia (faltan N días)", "Cuotas impagas", "Afiliado dado de baja") y ofrece "Registrar rechazo" o "Enviar a auditoría".  
* A2 · La prestación requiere auditoría y quien la pide no es administrador: solo puede enviarla a auditoría.  
* A3 · "Enviar a auditoría": pide una observación para el auditor (obligatoria si no corresponde autorizar o si la prestación requiere auditoría), crea la solicitud "En auditoría" con una copia de los controles y la deja para CU-21.  
* A4 · "Ver historial de usos" (CU-25) y "Enviar por WhatsApp" el estado de sus beneficios (RT-04).  
* E1 · Dato que no coincide con ningún afiliado: "No se encontró ningún afiliado con ese dato."

**Reglas de negocio**

* Carencia exigida: la mayor entre la carencia general del tipo de prestación (CU-46) y la de la regla del plan (si hay sin cargo, la de sin cargo; si hay escala, ninguna).  
* "Pagos al día" usa RT-05; el cálculo del beneficio, RT-06.  
* La numeración de autorizaciones es única y empieza en 1001\.  
* Historial: el administrador ve todas; el usuario ve las suyas de hoy y todas las que mandó a auditoría, con su resultado.

**Datos:** autorizaciones (N°, afiliado, prestación, fecha, resultado, motivo, estado de auditoría, observación, controles, usuario, auditor, respuesta), usos (pendiente de unidad).

**Criterios de aceptación**

* ☐ Un afiliado con una factura vencida obtiene "No corresponde autorizar: Cuotas impagas".  
* ☐ Autorizar una sesión de hiperbárica sin cargo deja 4 de 5 disponibles en un grupo Plus con más de 6 meses de antigüedad que no usó otras.  
* ☐ Un usuario no ve en el historial las autorizaciones de otros usuarios.

### **CU-21 · Auditar solicitudes de autorización**

**Actor:** administrador · **Pantalla:** Auditoría

**Precondiciones:** hay solicitudes "En auditoría".

**Flujo principal**

1. El sistema avisa de las pendientes al ingresar (CU-01), con una franja roja en las demás pantallas y un contador en el menú.  
2. En Auditoría lista cada solicitud: prestación, unidad, N°, fecha, quién la pidió, afiliado (plan y alta), observación y los controles al momento del pedido.  
3. El actor puede ver el historial del afiliado (CU-25) y decide "Aprobar" (comentario opcional) o "Rechazar" (motivo obligatorio).  
4. Al aprobar, el sistema la pasa a "Autorizada" y descuenta el beneficio si todavía no se había descontado. Al rechazar, a "Rechazada".  
5. Queda registrado quién auditó, cuándo y la respuesta; se ve en "Últimas resueltas" (20) y en el historial del usuario que la pidió.

**Errores:** E1 · Un usuario que no es administrador intenta decidir: "Solo un administrador puede auditar."

**Datos:** autorizaciones, usos.

**Criterios de aceptación**

* ☐ Aprobar dos veces la misma solicitud no descuenta dos veces el beneficio.  
* ☐ Rechazar sin motivo no está permitido.  
* ☐ Al quedar 0 pendientes desaparecen la franja roja y el contador.

### **CU-22 · Verificar afiliado y registrar uso en una unidad**

**Actor:** unidad de negocio (Biobárica, Laboratorio, Óptica y las creadas) · **Pantalla:** Verificar afiliado (modo unidad)

**Precondiciones:** la unidad y su usuario están activos.

**Flujo principal**

1. La unidad escribe DNI, CUIT, N° de afiliado o apellido y nombre (con sugerencias). Con 7 o más dígitos verifica sola; el DNI se formatea con puntos.  
2. El sistema muestra "Afiliado vigente", nombre, N° y plan; las promociones vigentes de esa unidad; cuánto ahorró en el año; y "Hoy dispone de" para cada prestación de la unidad.  
3. La unidad atiende y cobra con su propio sistema aplicando lo indicado.  
4. Registra el uso: cantidad y, opcionalmente, el precio cobrado.  
5. El sistema guarda el uso con N° propio de la unidad, lo descuenta del cupo y confirma "Uso registrado: sin cargo / N % de descuento / sin beneficio".

**Estados que muestra "Hoy dispone de"**

| Estado | Cuándo |
| :---- | :---- |
| SIN CARGO | Le quedan sesiones sin cargo en la ventana; indica cuántas quedan y qué pasa después |
| N % DE DESCUENTO | Ya no tiene sin cargo (o no aplica) y tiene descuento vigente |
| SIN BENEFICIO HOY | En carencia o sin regla; explica desde cuándo tendrá el beneficio |
| AUTORIZADA (SIN CARGO / N %) | Integral emitió una autorización para esa prestación en los últimos 30 días |

**Flujos alternativos y de error**

* A1 · Hay una autorización pendiente y la cantidad es 1: el registro confirma esa autorización (con el precio, si se carga) y no descuenta dos veces.  
* A2 · Escala por antigüedad: agrega "Desde el … pasa a N %".  
* A3 · "Beneficio completo de su plan": despliega los textos del folleto para esa unidad.  
* E1 · No encontrado: "No es afiliado de Integral del Este" (o "No encontré ningún afiliado con ese nombre").  
* E2 · Varios con ese nombre: pide elegir de la lista o escribir más.  
* E3 · Dado de baja: "no tiene beneficios. Se le cobra como particular."  
* E4 · No está al día: "hoy no tiene beneficios. Puede regularizar en Integral del Este."  
* E5 · Sin prestaciones cargadas: "Avisale a Integral del Este."

**Reglas de negocio:** cálculo según RT-06. El precio es opcional y solo sirve para calcular el ahorro. La unidad ve sus usos de hoy (N°, nombre e inicial del apellido, RT-08). Esc limpia la búsqueda.

**Datos:** usos en unidades (N° U+punto de venta+correlativo, unidad, afiliado, plan, fecha, hora, líneas: prestación, cantidad, sin cargo, porcentaje, precio, ahorro; saldos; usuario; autorización confirmada).

**Criterios de aceptación**

* ☐ Un afiliado Plus con 7 meses de antigüedad ve SIN CARGO en hiperbárica y "Le quedan 5 de 5" si nadie del grupo la usó en el año.  
* ☐ Con 8 meses de antigüedad en Plus, registrar 2 sesiones teniendo 1 sin cargo disponible guarda 1 sin cargo y 1 con 50 %.  
* ☐ La unidad no puede abrir ninguna otra pantalla ni pedir el padrón a la API.

### **CU-23 · Verificar afiliado y registrar consumo en un comercio**

**Actor:** comercio adherido · **Pantalla:** Verificar afiliado (modo comercio)

**Flujo principal**

1. El comercio pide el DNI o el N° de la credencial y toca "Verificar".  
2. Si corresponde, el sistema muestra "Tiene el beneficio", nombre, N°, plan, el texto del beneficio para su rubro y sus consumos del año en ese comercio (y usos del período si hay límite).  
3. El comercio carga qué compró o usó, el importe sin descuento y el porcentaje aplicado (propone el máximo).  
4. El sistema muestra ahorro y "Cobrar", y al registrar guarda el consumo.

**Errores** ("No tiene el beneficio" con el motivo): no es afiliado; dado de baja; no está al día; su plan no incluye este beneficio; todavía en carencia ("lo tiene desde el …"); alcanzó el límite de usos del período.

**Reglas de negocio:** el porcentaje no puede superar el máximo del rubro (CU-44). La carencia sale del texto del beneficio del plan contada desde el alta. El comercio solo busca por DNI o N°. Ve sus consumos de hoy con nombre e inicial (RT-08).

**Datos:** consumos en comercios (fecha, hora, usuario, comercio, rubro, afiliado, detalle, importe, porcentaje, ahorro, total).

**Criterios de aceptación**

* ☐ En Gimnasio (máximo 15 %), escribir 20 % queda en 15 %.  
* ☐ Con límite 1 por mes, el segundo intento del mes informa "Ya usó el beneficio 1 vez este mes (límite: 1)".  
* ☐ Un afiliado del Plan Integral no tiene el beneficio de Gimnasio (es solo Plus).

### **CU-24 · Consultar los usos en unidades**

**Actor:** administrador · **Pantalla:** Usos en unidades

**Flujo principal**

1. El actor elige unidad (o todas) y mes.  
2. El sistema muestra totales: usos registrados, sin cargo, con descuento, afiliados distintos y ahorro informado.  
3. Lista cada uso: fecha y hora, unidad, afiliado y N°, prestación y cantidad, beneficio aplicado, precio informado, ahorro y quién lo registró, con botón "Historial".

**Reglas de negocio:** Integral no factura prestaciones: cada unidad cobra con su propio sistema y punto de venta; acá solo se lleva la cuenta de los beneficios (v49).

**Criterios de aceptación**

* ☐ Las autorizaciones emitidas en Integral aparecen como uso con "(autorización N° …)" en "Registró".

### **CU-25 · Consultar el historial de beneficios de un afiliado**

**Actor:** usuario de atención, administrador · **Pantalla:** modal "Historial de beneficios" (desde Autorizaciones, Auditoría, Usos en unidades y la ficha)

**Flujo principal**

1. El sistema muestra afiliado, N°, plan y el ahorro del año.  
2. Muestra por prestación con beneficio: la regla en texto, desde cuándo rige, sin cargo usados de cuántos en la ventana, cuántos le quedan, usos y ahorro.  
3. Lista cada uso con fecha, unidad, prestación, cantidad, beneficio y lo que pagó, con "Ver" para el comprobante del uso.

**Criterios de aceptación**

* ☐ Con cupo por grupo, el uso de un integrante baja lo que le queda a los demás.

## **7\. Caja y cobranza**

### **CU-26 · Facturar las cuotas del mes**

**Actor:** administrador · **Pantalla:** Caja → "Facturar cuotas del mes"

**Precondiciones:** punto de venta configurado (CU-46); el mes es noviembre 2026 o posterior.

**Flujo principal**

1. El actor elige el mes.  
2. El sistema muestra cuántos grupos faltan facturar en ese mes y el total.  
3. El actor toca "Emitir N factura(s)".  
4. El sistema emite una factura por grupo y la deja "pendiente de CAE" para CU-27; informa "N facturas emitidas" y la última emisión (cantidad, mes, total, fecha).

**Alternativos y errores:** A1 · Todos los grupos ya tienen la factura del mes: el botón queda deshabilitado. E1 · Mes anterior a noviembre 2026: "El sistema factura desde noviembre 2026."

**Reglas de negocio**

* Importe \= suma de las cuotas de los integrantes activos cuya alta es anterior al fin del mes; si la promoción del grupo está vigente ese mes, se aplica el descuento redondeado al peso.  
* Solo se factura a grupos con importe mayor que 0 y sin factura de ese mes (no hay dos facturas del mismo grupo y mes).  
* Letra: A si el grupo factura como Responsable Inscripto; B en los demás casos. Receptor: consumidor final con DNI del titular, o razón social y CUIT.  
* Numeración PPPPP-NNNNNNNN por punto de venta y por letra, siguiendo el último número configurado.  
* Fecha de emisión: hoy, o el día 1 del mes si se factura por adelantado. Vencimiento: día configurado del mes facturado (10 por defecto).  
* Alícuota: la del plan (10,5 % para las cuotas). Se guarda el detalle por integrante, la bonificación y el mes N de M de la promoción.  
* La operación es atómica: si falla una factura, no se emite ninguna (SUPUESTO S-02).

**Datos:** facturas (N°, letra, receptor, alícuota, grupo, período, emisión, vencimiento, importe, base, promoción, líneas, CAE, vencimiento del CAE, estado, mail enviado).

**Criterios de aceptación**

* ☐ Correr la facturación dos veces para el mismo mes no duplica ninguna factura.  
* ☐ Un afiliado dado de alta el 5 de diciembre no se factura en noviembre.  
* ☐ Con último N° B configurado en 120, la primera factura B sale 00001-00000121.

### **CU-27 · Obtener el CAE de ARCA**

**Actor:** Sistema (worker) · **Pantalla:** ninguna (resultado visible en Caja → Facturas)

**Origen:** la maqueta simula el CAE "hasta conectar el servidor". Este caso describe lo mínimo para reemplazar la simulación; los detalles del web service están en el documento de Arquitectura.

**Flujo principal**

1. El worker toma las facturas pendientes de CAE en orden de número.  
2. Pide el CAE a ARCA con los datos de la factura (punto de venta, tipo A o B, receptor, importes, alícuota).  
3. Guarda el CAE y su vencimiento en la factura.

**Alternativos y errores:** E1 · ARCA no responde: reintenta; la factura sigue "pendiente de CAE" (P-04). E2 · ARCA rechaza: marca la factura con el error y avisa a los administradores.

**Reglas de negocio:** la numeración local tiene que coincidir con la de ARCA para ese punto de venta; ante una diferencia, el worker no factura y alerta.

**Criterios de aceptación**

* ☐ En homologación, una factura B de prueba obtiene CAE y se ve en la columna CAE.  
* ☐ Una factura sin CAE no se envía por mail (CU-28).

### **CU-28 · Enviar las facturas del mes por mail**

**Actor:** administrador; Sistema envía · **Pantalla:** Caja → "Enviar facturas por mail"

**Flujo principal**

1. El sistema muestra las facturas del mes, cuántas tienen email, cuántas no y cuántas ya se enviaron, y la lista de titulares sin email.  
2. El actor toca "Enviar N mails"; el sistema muestra un ejemplo del mail del primero con su factura en PDF adjunta.  
3. Al confirmar, el sistema encola un mail por factura (desde sistema@integraldeleste.com.ar, con el PDF) y las marca enviadas.

**Alternativos:** A1 · "Editar el texto del mail" lleva a CU-48. A2 · Titulares sin email: se les manda por WhatsApp desde cada factura (CU-31).

**Reglas de negocio:** plantilla "Factura de la cuota" con {nombre}, {periodo}, {importe}, {vencimiento}, {plan}, {numero}. Una factura ya enviada no se reenvía en el masivo.

**Criterios de aceptación**

* ☐ De 24 facturas con 20 emails y 5 ya enviadas, el botón dice "Enviar 15 mails".  
* ☐ Un mail rebotado queda marcado y se informa al administrador.

### **CU-29 · Registrar cobros por débito automático**

**Actor:** administrador · **Pantalla:** Caja → "Débito automático"

**Flujo principal**

1. El sistema lista las facturas pendientes de los grupos con forma de pago "Débito automático": titular, mes, importe y fecha de pago (hoy por defecto).  
2. El actor ajusta la fecha y toca "Registrar" en cada una cuando entra el pago.  
3. El sistema emite el recibo con medio "Débito automático" y la factura pasa a pagada.

**Alternativos:** A1 · "Registrar todas con fecha de hoy".

**Reglas de negocio:** Mercado Pago cobra en fechas distintas, por eso se registra de a una (P-05). El recibo indica "Cobro por débito automático".

**Criterios de aceptación**

* ☐ Un recibo registrado con fecha 12/11 aparece en la cuenta corriente el 12/11, no el día de carga.

### **CU-30 · Cobrar cuotas en mostrador**

**Actor:** administrador · **Pantalla:** Caja → "Cobranza en mostrador"

**Flujo principal**

1. El sistema lista las cuotas pendientes ordenadas por vencimiento, con "Vencida" o "A vencer".  
2. El actor elige el medio de pago (propone la forma de pago del grupo) y toca "Cobrar".  
3. El sistema emite el recibo por el total de la factura, la marca pagada y abre el recibo para imprimir o enviar (CU-31).  
4. Si está activado el envío automático (CU-48) y el grupo tiene email, el Sistema le manda el recibo por mail.

**Reglas de negocio**

* Medios: Efectivo, Transferencia bancaria, Débito automático, Tarjeta de crédito, Mercado Pago.  
* Un recibo cancela una factura entera; no hay pagos parciales (P-06).  
* Numeración de recibos R \+ punto de venta (5 dígitos) \+ correlativo (8 dígitos).  
* "Recibos emitidos" muestra los últimos 30 con "Ver".

**Datos:** recibos (N°, factura, grupo, fecha, importe, medio, masivo, usuario).

**Criterios de aceptación**

* ☐ Cobrar la misma factura dos veces desde dos equipos genera un solo recibo; el segundo intento informa que ya está pagada.  
* ☐ El recibo muestra el importe en letras ("son pesos veinticinco mil").

### **CU-31 · Ver, imprimir y enviar una factura o un recibo**

**Actor:** administrador · **Pantalla:** modal de factura o recibo (desde Caja y Cuentas corrientes)

**Flujo principal**

1. El actor toca "Ver" en una factura o recibo.  
2. **Factura:** encabezado fiscal de Grupo del Este Salud S.A.S. (domicilio, IVA Responsable Inscripto, CUIT 30-71644749-5, Ingresos Brutos, inicio de actividades), letra y código (B 006, A 001), N°, fecha, receptor, plan, período, vencimiento, detalle por integrante, subtotal, bonificación, total, bloque de IVA, CAE y estado.  
3. **Recibo:** letra X "No válido como factura", quién paga (DNI y N°), importe en números y letras, concepto (cuota, plan), factura que cancela y medio de pago.  
4. El actor elige: Enviar por mail, Descargar PDF, Compartir PDF (RT-04) o Imprimir.

**Reglas de negocio**

* Factura B: leyenda "Régimen de Transparencia Fiscal al Consumidor (Ley 27.743)" con IVA contenido y otros impuestos nacionales indirectos. Factura A: neto gravado e IVA discriminado.  
* Sin alícuota cargada: "alícuota a confirmar" y aviso para cargarla.  
* No figura quién cobró ni quién emitió (RT-10).  
* "Enviar por mail" usa la plantilla de factura o recibo y el email del grupo; sin email avisa que hay que cargarlo en la ficha.

**Criterios de aceptación**

* ☐ El PDF de una factura B de \$ 25.000 al 10,5 % muestra IVA contenido \$ 2.375,57.  
* ☐ El PDF del recibo no menciona al usuario que cobró.

### **CU-32 · Consultar la cuenta corriente de un grupo**

**Actor:** administrador · **Pantalla:** Cuentas corrientes (o "Ver cuenta corriente" desde la ficha)

**Flujo principal**

1. El sistema lista los grupos con integrantes activos o con saldo: titular, integrantes, medio de pago, cuota actual, saldo y estado (RT-05).  
2. El actor elige un grupo; el sistema muestra sus integrantes y los movimientos: saldo inicial ("Inicio del sistema: al día" al 31/10/2026), facturas en el debe, recibos en el haber y saldo acumulado, con "Ver" en cada comprobante.  
3. Si hay cuotas pendientes, el actor elige medio y toca "Cobrar N cuota(s) y emitir recibo": el sistema emite un recibo por factura.

**Criterios de aceptación**

* ☐ El saldo final coincide con la suma de facturas menos la suma de recibos del grupo.  
* ☐ Cobrar 3 cuotas pendientes genera 3 recibos, uno por factura.

## **8\. Gestión**

### **CU-33 · Registrar egresos**

**Actor:** administrador · **Pantalla:** Egresos

**Flujo principal**

1. El actor carga fecha, categoría, proveedor (con sugerencias de los ya cargados, que completan categoría, CUIT y comprobante), CUIT opcional, detalle, tipo y N° de comprobante, importe total, medio de pago y si es un gasto fijo mensual.  
2. Si el comprobante es Factura A, carga el IVA incluido (crédito fiscal) o lo calcula con los botones 21 % y 10,5 %.  
3. Toca "Guardar egreso"; el sistema lo registra y lo asienta en Contabilidad (CU-34).  
4. El resumen del mes muestra cobrado de cuotas, egresos y resultado (ganancia o pérdida), los egresos por categoría y la lista del mes.

**Alternativos y errores**

* A1 · "Gastos fijos sin cargar este mes": cada fijo de meses anteriores que todavía no se cargó tiene "Cargar", que completa el formulario con los datos del último.  
* A2 · "Borrar" un egreso: lo elimina y lo registra en el historial con sus datos.  
* E1 · Importe vacío o 0: "Cargá el importe." E2 · IVA mayor o igual al total: no se guarda.

**Reglas de negocio**

* Categorías: alquiler, sueldos y cargas sociales, honorarios, insumos y librería, servicios, sistema y web, seguro de sepelio, ayudas económicas a afiliados, publicidad, comisiones bancarias y de cobro, impuestos y tasas, otros gastos de administración.  
* Comprobantes: Factura A, B o C, Ticket, Recibo, Sin comprobante. Solo la Factura A discrimina IVA.  
* Un gasto fijo se reconoce por proveedor \+ categoría.

**Datos:** egresos (fecha, categoría, proveedor, CUIT, detalle, comprobante, N°, importe, IVA, medio, fijo, usuario, fecha de carga).

**Criterios de aceptación**

* ☐ Un egreso Factura A de \$ 12.100 con IVA 21 % calcula IVA \$ 2.100.  
* ☐ Si el alquiler se cargó como fijo en noviembre, en diciembre aparece en "Gastos fijos sin cargar" hasta que se cargue.

### **CU-34 · Consultar la contabilidad**

**Actor:** administrador · **Pantalla:** Contabilidad

**Flujo principal**

1. El sistema muestra el plan de cuentas con el saldo de cada cuenta al día de hoy (los rubros suman sus cuentas).  
2. Muestra el libro diario con los últimos 12 asientos.

**Reglas de negocio** (asientos automáticos)

| Origen | Debe | Haber |
| :---- | :---- | :---- |
| Factura de cuota | 1.3 Cuotas a cobrar | 4.1 Cuotas de afiliados |
| Recibo en efectivo | 1.1 Caja | 1.3 Cuotas a cobrar |
| Recibo por otro medio | 1.2 Banco y billeteras | 1.3 Cuotas a cobrar |
| Egreso | Cuenta de su categoría (5.x) por el neto, y 1.4 IVA crédito fiscal por el IVA | 1.1 Caja (efectivo) o 1.2 Banco y billeteras |

Plan de cuentas: 1 Activo (1.1 a 1.4), 2 Pasivo (2.1 Proveedores a pagar), 3 Patrimonio neto (3.1 Capital), 4 Ingresos (4.1), 5 Egresos (5.1 a 5.12). En la maqueta los asientos se calculan al mostrar, no se guardan (P-11).

**Criterios de aceptación**

* ☐ Después de facturar \$ 100.000 y cobrar \$ 60.000 en efectivo, 1.3 queda en \$ 40.000 y 1.1 en \$ 60.000.  
* ☐ La suma del debe y la del haber del libro diario son iguales.

### **CU-35 · Consultar estadísticas**

**Actor:** administrador · **Pantalla:** Estadísticas

**Flujo principal:** el sistema muestra, de arriba hacia abajo:

1. Resumen mensual para los afiliados (CU-36) y Retención de afiliados (CU-13).  
2. Ahorro de los afiliados por período (este mes, 3 meses, este año): total, grupos que ahorraron, promedio por grupo, ahorro por cada \$ 1 de cuota cobrada, ahorro por unidad o comercio y los 20 grupos que más ahorraron con "Enviar resumen" (CU-37).  
3. Indicadores: afiliados activos y grupos, cuotas por mes del padrón, facturado del último mes y deuda vencida con grupos atrasados.  
4. Afiliados por categoría y cobrado por medio de pago.  
5. Consultas atendidas: este mes, total, por canal, no afiliados, planes más consultados y las últimas 15\.  
6. Consumo en comercios adheridos: por comercio (este mes, total, afiliados, vendido, ahorro) y los 10 afiliados que más los usan.  
7. Consumo de beneficios por unidad o comercio y beneficio, por plan; lo más usado y los beneficios que nadie usó en el período.  
8. Facturado y cobrado por mes.

**Reglas de negocio**

* Los usos sin precio informado ni valor de referencia no suman ahorro; el sistema avisa cuántos son y dónde cargar el valor.  
* Del 1 al 15 de cada mes, si hay grupos que usaron beneficios el mes anterior y no recibieron el resumen, todas las pantallas de administrador muestran una franja "Ver y enviar".

**Criterios de aceptación**

* ☐ La deuda vencida coincide con la suma de facturas pendientes con vencimiento anterior a hoy.  
* ☐ El ahorro total coincide con la suma del informe "Usos de beneficios" del mismo período.

### **CU-36 · Enviar el resumen mensual de beneficios**

**Actor:** administrador; Sistema envía · **Pantalla:** Estadísticas → "Resumen mensual para los afiliados"

**Flujo principal**

1. El actor elige el mes (por defecto el anterior; no puede ser futuro).  
2. El sistema lista los grupos que usaron beneficios ese mes: qué usaron, ahorro y estado del envío.  
3. "Enviar por mail a N afiliados" muestra un ejemplo y, al confirmar, encola los mails a quienes tienen email y no lo recibieron, y los marca enviados.  
4. Para los que no tienen email, "WhatsApp" en cada fila arma el PDF del resumen y lo marca enviado.

**Reglas de negocio:** plantilla "Resumen mensual de beneficios" con {detalle} (cada beneficio, veces, sin cargo y ahorro) e {importe} (ahorro total). Un grupo recibe un solo resumen por mes.

**Criterios de aceptación**

* ☐ Un grupo sin usos en el mes no aparece en la lista.  
* ☐ Repetir el envío no duplica mails.

### **CU-37 · Enviar el resumen de ahorro a un titular**

**Actor:** administrador · **Pantalla:** Estadísticas → Ahorro de los afiliados → "Enviar resumen"

**Flujo principal:** 1\. El actor elige el período y toca "Enviar resumen" en un grupo. 2\. El sistema arma el PDF "Tu ahorro" con cada beneficio, veces, sin cargo y ahorro, el total y las cuotas pagadas en el período, y abre el envío (RT-04).

**Reglas de negocio:** si el ahorro supera lo pagado de cuota en el período, el mensaje lo destaca ("¡Ahorraste más de lo que pagaste\!").

**Criterios de aceptación**

* ☐ El total del PDF coincide con el ahorro del grupo en la tabla para ese período.

### **CU-38 · Descargar informes**

**Actor:** administrador · **Pantalla:** Informes

**Flujo principal**

1. El actor elige el informe y el período (este mes, mes anterior, un mes puntual, este año, año anterior o entre dos fechas).  
2. El sistema muestra la vista previa (hasta 60 filas) con la cantidad total de filas y los totales.  
3. El actor toca "Descargar Excel" o "Descargar PDF".

**Informes disponibles**

| Informe | Columnas principales |
| :---- | :---- |
| Movimientos de caja | Fecha, tipo, concepto, medio, ingreso, egreso, saldo acumulado |
| Facturación de cuotas | Fecha, comprobante, titular, DNI o CUIT, período, neto, IVA, total, estado |
| Egresos | Fecha, categoría, proveedor, CUIT, comprobante, detalle, medio, IVA, importe |
| Libro diario | Fecha, asiento, cuenta, debe, haber |
| Usos de beneficios | Fecha, unidad o comercio, afiliado, N°, plan, prestación, cantidad, beneficio, precio, ahorro, quién registró |
| Altas y bajas | Fecha, movimiento, N°, nombre, DNI, plan, categoría, cuota |
| Padrón de afiliados | Activos hoy (no depende del período): N°, nombre, DNI, titular, plan, categoría, cuota, alta, teléfono, forma de pago |
| Resumen estadístico | Activos, grupos, altas, bajas, facturas, facturado, cobrado, egresos, resultado, deuda vencida, usos, ahorro, consultas, autorizaciones |

**Reglas de negocio:** encabezado con el nombre del informe, Grupo del Este Salud S.A.S., CUIT, período y (en el PDF) fecha de emisión y usuario. Montos en formato \$ \#.\#\#0,00. Nombre del archivo "Integral del Este \- … \- período". PDF horizontal si tiene más de 6 columnas.

**Criterios de aceptación**

* ☐ El Excel trae todas las filas aunque la vista previa muestre 60\.  
* ☐ "Entre dos fechas" con las fechas invertidas las ordena solo.  
* ☐ Un período sin datos genera el PDF con "No hay datos en este período".

## **9\. Configuración**

Todos estos casos son de administradores. Un usuario que entra a Configuración solo ve "Mi cuenta" (cambiar clave, salir) y las versiones del sistema. Los cambios marcados con (C) pasan por la confirmación con clave de CU-49.

### **CU-39 · Gestionar usuarios**

**Pantalla:** Configuración → Usuarios

**Flujo principal**

1. El sistema lista usuarios con rol (Administrador, Usuario, Unidad de negocio o Comercio con su nombre), qué ve (Todo, "N de 5" o Verificar afiliado) y estado.  
2. **Alta:** el actor escribe nombre, clave inicial (si la deja vacía, es igual al nombre), tipo (usuario interno, administrador, unidad de negocio, comercio adherido) y, para comercios, nombre y rubro.  
3. **Opciones** de un usuario: cambiar clave, hacer o quitar administrador, dar de baja o reactivar, y elegir las secciones que ve un usuario interno.  
4. Cada cambio queda en el historial.

**Errores:** E1 nombre de usuario ya existente; E2 clave de menos de 4 caracteres; E3 comercio sin nombre; E4 unidad que no coincide con una existente; E5 quitar o dar de baja al último administrador activo: "Tiene que quedar al menos un administrador."

**Reglas de negocio**

* Un usuario interno nuevo ve Altas, Cotizador, Autorizaciones, Beneficios y Quiere darse de baja; un administrador ve todo; unidades y comercios solo Verificar afiliado.  
* Nadie puede cambiar su propio rol ni darse de baja a sí mismo.  
* El nombre de usuario es único sin distinguir mayúsculas ni tildes.

**Criterios de aceptación**

* ☐ Un usuario sin "Cotizador" en sus secciones recibe 403 al pedir la cotización a la API.  
* ☐ No se puede crear "belen" si existe "Belén".

### **CU-40 · Gestionar prestaciones y valores de referencia (C)**

**Pantalla:** Configuración → Prestaciones y descuentos de afiliado

**Flujo principal**

1. El sistema muestra la fecha de la última actualización de precios y, por prestación: unidad, tipo, valor de referencia, última actualización (fecha, hace cuánto, quién) y "Requiere auditoría".  
2. El actor cambia valores o la marca de auditoría y toca "Guardar cambios" (C).  
3. "Quitar" o "Volver a usar" una prestación (C).  
4. "Agregar": nombre, unidad y tipo (Consultas, Estudios y prácticas, Óptica y biobárica).

**Errores:** E1 la prestación ya existe con ese nombre en esa unidad.

**Reglas de negocio:** Integral no factura prestaciones: el valor sirve para calcular el ahorro (RT-06). Al cambiar un valor se guarda fecha y usuario. Una prestación quitada no aparece en Autorizaciones ni en las unidades, pero sus usos se conservan.

**Criterios de aceptación**

* ☐ Cambiar el valor de la sesión hiperbárica actualiza "Última actualización" con la fecha y el usuario.  
* ☐ Marcar "Requiere auditoría" hace que un usuario solo pueda enviarla a auditoría (CU-20).

### **CU-41 · Importar precios desde PDF o Excel**

**Pantalla:** Configuración → Prestaciones → "Actualizar desde PDF o Excel"

**Flujo principal**

1. El actor sube un PDF con texto, un Excel (xlsx, xls), un CSV o un TXT.  
2. El sistema lee los renglones con nombre y precio; en Excel usa solo las hojas llamadas Nomenclador, Precios o Lista si existen.  
3. Muestra la vista previa: de qué unidad es la lista (la detecta si el archivo nombra una sola), qué precio usar si hay varios por renglón, y por renglón la prestación relacionada (por parecido de nombre), precio actual, precio nuevo y cambio (sube o baja N %, igual, nueva).  
4. El actor corrige relaciones y precios, elige "Agregar como nueva" (con unidad y tipo) o "No usar este renglón", y marca qué prestaciones que no figuran quiere quitar.  
5. "Aplicar cambios" actualiza precios, agrega y quita, guarda fecha y usuario, y lo resume en el historial.

**Errores:** E1 PDF escaneado (sin texto) o sin renglones con precio: "No encontré precios". E2 archivo ilegible: "No se pudo leer el archivo".

**Reglas de negocio:** un renglón es válido si tiene un nombre de 3 o más letras y un monto de 100 o más (o con \$); se ignoran porcentajes, años, fechas y renglones de total o encabezado. Aplicar requiere el permiso "Modificar montos".

**Criterios de aceptación**

* ☐ Un Excel con hoja "Instrucciones" y hoja "Nomenclador" solo lee la segunda.  
* ☐ Un renglón "Consulta clínica \$ 18.500" se relaciona con "Consulta clínica, cardiología o pediatría".

### **CU-42 · Configurar reglas de beneficios por plan (C)**

**Pantalla:** Configuración → Reglas de beneficios por plan

**Flujo principal:** 1\. Por plan y prestación activa el actor edita: sin cargo, cada (mes, semestre, año), por grupo, carencia sin cargo (días), descuento %, cómo (fijo u "hasta"), carencia del descuento (días), escala (días:porcentaje) y máximo con descuento por año. 2\. "Guardar reglas" (C).

**Reglas de negocio:** el significado de cada campo está en RT-06. Requiere el permiso "Modificar montos". Valores iniciales cargados del PDF de beneficios (por ejemplo, óptica Integral con escala 0:10, 90:20, 180:30, 365:50).

**Criterios de aceptación**

* ☐ Cambiar el sin cargo de hiperbárica de 5 a 12 muestra la alerta "Sesiones sin cargo: 5 → 12" antes de guardar.  
* ☐ Con la escala "0:10, 90:20", un afiliado tiene 10 % hasta el día 89 de antigüedad y 20 % desde el día 90\.

### **CU-43 · Gestionar unidades de negocio**

**Pantalla:** Configuración → Unidades de negocio

**Flujo principal**

1. El sistema lista las unidades activas con su usuario, sus prestaciones y el beneficio resumido por plan.  
2. **Crear:** nombre, servicio que presta, carencia en meses (0 a 24), descuento y sesiones sin cargo por plan, cada cuánto se renuevan y texto del beneficio. El sistema crea la unidad, su prestación, las reglas por plan y el usuario (con clave igual al nombre).  
3. **Editar:** nombre (solo las creadas), texto del beneficio y carencia (solo las creadas), nombre de usuario y clave nueva. Pide la clave del administrador.  
4. **Dar de baja:** la unidad, sus prestaciones y su usuario quedan inactivos; su historial se conserva. **Reactivar** los vuelve a activar.  
5. **Eliminar:** solo si nunca registró usos y no es una de las originales.

**Errores:** E1 ya existe una unidad o un usuario con ese nombre; E2 clave nueva de menos de 4 caracteres; E3 clave del administrador incorrecta; E4 "No cambiaste nada".

**Reglas de negocio:** las originales (CMDE, Óptica, Biobárica, Laboratorio) no cambian de nombre ni se eliminan. Si se renombra el usuario sin dar clave nueva, la clave pasa a ser el nuevo nombre. Las dadas de baja se ven aparte con "Ver unidades dadas de baja". Cambiar la carencia actualiza las reglas de sus prestaciones (meses × 30 días).

**Criterios de aceptación**

* ☐ Una unidad dada de baja no puede ingresar ni verificar afiliados.  
* ☐ Una unidad con usos no ofrece "Eliminar".

### **CU-44 · Configurar beneficios de comercios adheridos (C)**

**Pantalla:** Configuración → Beneficios en comercios adheridos

**Flujo principal:** 1\. Por rubro (con sus comercios), el actor edita descuento máximo %, límite de usos por afiliado (0 \= sin límite) y cada cuánto (mes, semestre, año). 2\. "Guardar" (C).

**Reglas de negocio:** valores iniciales: Gimnasio 15 %, Leguan 10 %, Tiendas de indumentaria 20 %, Barbería 20 %, Laboratorio 30 %, Farmacia 30 %, Odontología 50 %, Óptica 50 %, Viajes 0 %, Electrodomésticos 0 %.

**Criterios de aceptación**

* ☐ Con límite 2 por semestre, el tercer consumo del semestre se rechaza (CU-23).

### **CU-45 · Configurar precios por plan y categoría (C)**

**Pantalla:** Configuración → Precios por plan y categoría

**Flujo principal:** 1\. El actor edita la cuota mensual de cada categoría en cada plan y la alícuota de IVA de cada plan (a confirmar, 21 %, 10,5 %, exento). 2\. Opcionalmente marca "Aplicar también a la cuota de los afiliados actuales". 3\. "Guardar precios" (C).

**Reglas de negocio**

* Precio 0 \= el plan no se puede vender en esa categoría (CU-06, CU-15).  
* Con "aplicar a actuales", se actualiza solo la cuota de afiliados de ese plan y categoría cuya cuota era igual al precio anterior, y nunca a 0\.  
* Requiere el permiso "Modificar montos".  
* Precios vigentes (v30): Integral \$ 15.000; Plus \$ 25.000 (+64: \$ 29.000); adicionales con 25 % (menor de 18), 20 % (18 a 49\) y 10 % (50 a 64\) de descuento; Salud \$ 10.000; Sepelio \$ 10.000 (\$ 14.000 \+64).

**Criterios de aceptación**

* ☐ Sin "aplicar a actuales", las cuotas guardadas no cambian y el mensaje dice "para las próximas altas".  
* ☐ Un afiliado con cuota negociada distinta al precio anterior no se modifica.

### **CU-46 · Configurar parámetros de facturación (C)**

**Pantalla:** Configuración → Facturación e IVA, Puntos de venta (ARCA), Vencimiento y carencias

**Flujo principal**

1. **Facturación e IVA** (informativo): cuotas gravadas al 10,5 %, Factura B a consumidores finales y exentos, A a Responsables Inscriptos; las prestaciones las factura cada unidad con su sistema.  
2. **Puntos de venta:** número del punto de venta de cuotas (1 a 99.999), último N° de Factura B y de Factura A emitidos; el sistema muestra la próxima factura B.  
3. **Vencimiento y carencias:** día de vencimiento (1 a 28\) y carencia general en días (0 a 730\) para consultas, estudios y prácticas, y óptica y biobárica.  
4. Guardar cada bloque (C).

**Reglas de negocio:** el punto de venta tiene que ser del tipo "Web Services" en ARCA. Los números de ejemplo se confirman con el contador.

**Criterios de aceptación**

* ☐ Un día de vencimiento 31 se guarda como 28\.  
* ☐ La próxima factura mostrada coincide con la primera que emite CU-26.

### **CU-47 · Configurar qué acciones requieren administrador (C)**

**Pantalla:** Configuración → Qué requiere administrador

**Flujo principal:** el actor marca o desmarca las 4 acciones de CU-04 y toca "Guardar permisos" (C). Queda registrado "requiere administrador" o "libre".

**Criterios de aceptación**

* ☐ Desmarcar "Modificar montos" permite a un usuario autorizado (CU-08) cambiar la cuota sin pedir clave de administrador.

### **CU-48 · Editar los textos de los mails (C)**

**Pantalla:** Configuración → Mails a los afiliados

**Flujo principal:** 1\. El actor edita asunto y texto de las 4 plantillas: factura de la cuota, recibo de pago, resumen mensual de beneficios y promociones. 2\. Marca o no "Mandar el recibo por mail automáticamente al cobrar". 3\. "Guardar textos" (C).

**Reglas de negocio:** variables: {nombre} (primer nombre), {periodo}, {importe}, {vencimiento}, {plan}, {numero}; el resumen agrega {detalle}; las promociones {titulo}, {beneficio}, {lugar}, {texto}, {desde}, {hasta}. Una variable desconocida queda escrita tal cual.

**Criterios de aceptación**

* ☐ Cambiar el asunto de la factura se refleja en la vista previa del envío masivo (CU-28).

### **CU-49 · Confirmar y deshacer cambios de configuración**

**Pantalla:** modal "Confirmá los cambios" y Configuración → Últimos cambios de configuración

**Flujo principal**

1. Al guardar un bloque marcado (C), el sistema calcula los cambios sin aplicarlos y muestra la lista exacta "antes → después".  
2. Si hay valores fuera de lo común, los muestra en rojo: precio que cambia más de 50 % o pasa a \$ 0, sesiones sin cargo mayores que 10 o con cambio de 3 o más, descuento mayor que 60 %, carencia mayor que 365 días o que pasa a 0\.  
3. El actor confirma con su clave; el sistema aplica, registra y guarda una instantánea para deshacer.  
4. **Deshacer:** en "Últimos cambios" solo el más reciente tiene "Deshacer"; tras confirmar, vuelve todo a como estaba y lo registra.

**Alternativos y errores:** A1 sin cambios: "No hubo cambios para guardar." E1 clave incorrecta: no se aplica nada. Mientras se edita, los campos modificados se marcan en amarillo.

**Reglas de negocio:** se guardan los 10 últimos cambios. Deshacer restaura reglas, planes y precios, prestaciones, carencias, categorías, permisos y configuración; no toca afiliados, facturas ni cobros.

**Criterios de aceptación**

* ☐ Deshacer un cambio de precios devuelve los precios anteriores, pero no las cuotas ya actualizadas de los afiliados (P-12).  
* ☐ Solo el cambio más reciente se puede deshacer.

### **CU-50 · Gestionar copias de seguridad**

**Pantalla:** Configuración → Copias de seguridad

**Flujo principal (maqueta)**

1. El sistema muestra la fecha de la última copia, hace cuánto y quién, en rojo si pasaron más de 7 días.  
2. "Descargar copia ahora" baja todos los datos (afiliados, cuotas, facturas, cobros, usos, egresos, usuarios y configuración).  
3. "Restaurar una copia": muestra fecha, autor y cantidades de la copia, advierte que se pierde lo cargado después, descarga antes una copia del estado actual y pide la clave del administrador.

**En el sistema instalado:** las copias son automáticas (Arquitectura, sección 5\) y la pantalla muestra el estado de la última. Si se mantienen la descarga y la restauración desde la pantalla es la P-13.

**Criterios de aceptación**

* ☐ Con la última copia correcta hace 8 días, el estado se ve en rojo.

### **CU-51 · Volver a cargar el padrón inicial**

**Pantalla:** Configuración → Padrón

**Flujo principal:** 1\. El actor toca "Volver a cargar el padrón del Excel" (permiso "Volver a cargar el padrón"). 2\. El sistema advierte que se borran altas, bajas, facturas y cobros, y que se conservan usuarios, permisos, prestaciones e historial. 3\. Al confirmar, vuelve a los 24 afiliados al día al 31/10/2026.

**Reglas de negocio:** es una función de la demo; su uso en producción es la P-14 (SUPUESTO S-03: no se habilita en producción).

### **CU-52 · Consultar historial de cambios y versiones**

**Pantalla:** Configuración → Historial de cambios y Versiones del sistema; número de versión en la barra superior

**Flujo principal:** 1\. El administrador ve los últimos 60 cambios: fecha y hora, acción, detalle y quién (con "autorizó …" si corresponde). 2\. Todos los usuarios ven la lista de versiones con fecha y descripción, y al tocar "vN" en la barra, las novedades.

**Reglas de negocio:** RT-07. El historial no se puede editar ni borrar desde la aplicación.

**Criterios de aceptación**

* ☐ Una baja confirmada aparece en el historial con el motivo.  
* ☐ Un usuario de atención no puede leer el historial por la API.

## **10\. Matriz caso de uso ↔ pantalla**

Cada caso de uso apunta a una pantalla de la maqueta (su ruta \#vista), al elemento donde ocurre y a la función o acción (data-act) del código de la maqueta que lo implementa, para que el programador pueda abrirla y comparar.

| CU | Pantalla (ruta) | Elemento | Referencia en la maqueta |
| :---- | :---- | :---- | :---- |
| CU-01 | Ingreso (sin ruta) | Usuario, clave, lista de recordados | mostrarLogin, iniciarSesion |
| CU-02 | Barra superior (todas) | Botón Salir; cierre por inactividad | salir, INACTIVO\_MIN |
| CU-03 | Barra superior; \#config → Mi cuenta | Cambiar mi clave | modalClave, mi-clave |
| CU-04 | Modal "Requiere un administrador" | Usuario y clave del administrador | pedirAdmin, PERMISOS |
| CU-05 | \#afiliados | Buscador, filtro de estado, tabla | V.afiliados |
| CU-06 | \#altas | Formulario de alta y grupo familiar | vistaAlta, afterAlta, crearGrupo |
| CU-07 | \#afiliados → ficha | Panel lateral | ficha |
| CU-08 | Ficha → Editar datos | Formulario de edición | ficha(id, true), edit |
| CU-09 | Ficha → Promoción | Modal de promoción del grupo | modalPromoGrupo |
| CU-10 | Ficha → Dar de baja | Modal "Antes de dar de baja a …" | modalRetencion, ofertasRetencion |
| CU-11 | Ficha → Reactivar | Botón | reactivar |
| CU-12 | \#retencion | Pasos 1 a 6 | V.retencion, GUION, rt-\* |
| CU-13 | \#estadisticas → Retención de afiliados | Para resolver | panelRetencion, rt-abrir, rt-conf-baja |
| CU-14 | Franja de cumpleaños (todas) | Saludar a … | bandaCumple, cumple-wa |
| CU-15 | \#cotizador | Plan, integrantes, tarjetas, envío | V.cotizador, wa-cot, cot-iniciar |
| CU-16 | \#beneficios | Buscador, filtro, tabla, condiciones | V.beneficios, BENEF, CONDICIONES |
| CU-17 | \#beneficios | Enviar información a una persona | panelConsulta, at-pdf, atRegistrar |
| CU-18 | \#promociones | Formulario y Promociones armadas | V.promociones, pr-guardar, pr-del |
| CU-19 | \#promociones → Enviar a los afiliados | Mail masivo, WhatsApp de a uno | vEnvioPromo, pr-mail, pr-wa |
| CU-20 | \#autorizaciones | Nueva autorización, verificación, historial | V.autorizaciones, verificar, autorizar, aud-enviar |
| CU-21 | \#auditoria | Pendientes y últimas resueltas | V.auditoria, aud-decidir |
| CU-22 | \#verificar (unidad) | Verificar afiliado, Hoy dispone de, Registrar uso | vUnidad, estadoUnidad, afterUnidad |
| CU-23 | \#verificar (comercio) | Verificar afiliado, Cargar consumo | V.verificar, verificarComercio |
| CU-24 | \#prestaciones ("Usos en unidades") | Filtros, totales, tabla | V.prestaciones (segunda definición) |
| CU-25 | Modal "Historial de beneficios" | Tabla de beneficios y de usos | verHistorial, htmlBeneficios, saldoBeneficios |
| CU-26 | \#caja | Facturar cuotas del mes, Facturas | V.caja, facturar, emitirFactura |
| CU-27 | \#caja → Facturas (columna CAE) | — | genCAE (simulado) |
| CU-28 | \#caja | Enviar facturas por mail | mail-masivo, mail-masivo-ok |
| CU-29 | \#caja | Débito automático | debito, debito-uno, cobrar |
| CU-30 | \#caja | Cobranza en mostrador, Recibos emitidos | cobrar (acción y función) |
| CU-31 | Modal de factura o recibo | Enviar por mail, PDF, Compartir, Imprimir | verFacturaCuota, verRecibo, pdf-fc, pdf-rec, mail-uno |
| CU-32 | \#cuentas | Lista de grupos y detalle | V.cuentas, cuenta, cobrar-todo |
| CU-33 | \#egresos | Cargar egreso, resumen, lista | V.egresos, eg-fijo, eg-del |
| CU-34 | \#contabilidad | Plan de cuentas, libro diario | V.contabilidad, asientos, CUENTAS |
| CU-35 | \#estadisticas | Todos los paneles | V.estadisticas, panelAhorro, panelConsumo |
| CU-36 | \#estadisticas | Resumen mensual para los afiliados | panelResumenMes, res-mail, res-wa |
| CU-37 | \#estadisticas → Ahorro de los afiliados | Enviar resumen | wa-aho, textoAhorro, infoAhorro |
| CU-38 | \#informes | Qué informe, qué período, vista previa | V.informes, datosInforme, informeExcel, informePdf |
| CU-39 | \#config | Usuarios | fnu, u-menu, u-clave, u-rol, u-estado, u-vistas |
| CU-40 | \#config | Prestaciones y descuentos de afiliado | guardar-prest, pr-toggle, fnp |
| CU-41 | \#config → Actualizar desde PDF o Excel | Modal de importación | importarArchivo, modalImport, aplicarImport |
| CU-42 | \#config | Reglas de beneficios por plan | guardar-reglas, reglasIniciales |
| CU-43 | \#config | Unidades de negocio | fnun, modalUnidad, modalUnidadBaja |
| CU-44 | \#config | Beneficios en comercios adheridos | guardar-rubros, RUBROS0 |
| CU-45 | \#config | Precios por plan y categoría | guardar-precios, PRECIOS30 |
| CU-46 | \#config | Facturación e IVA, Puntos de venta, Vencimiento y carencias | guardar-pv, guardar-cfg |
| CU-47 | \#config | Qué requiere administrador | guardar-permisos |
| CU-48 | \#config | Mails a los afiliados | guardar-mail, MAIL0 |
| CU-49 | Modal "Confirmá los cambios"; \#config → Últimos cambios | Lista de cambios, alertas, Deshacer | confirmarConfig, alertasCambio, deshacerConfig |
| CU-50 | \#config | Copias de seguridad | bajarCopia, subirCopia |
| CU-51 | \#config | Padrón | reset-padron, reset-ok |
| CU-52 | \#config → Historial y Versiones; "vN" en la barra | Tablas | listaVersiones, novedades, logCambio |

**Pantallas sin caso de uso propio:** \#inicio (accesos grandes del usuario de atención, que llevan a CU-06, CU-12, CU-15, CU-16 y CU-20) y \#sinacceso (usuario sin secciones). La primera definición de V.prestaciones (facturar prestaciones con factura o recibo y descuento adicional) quedó reemplazada en la v49 por "Usos en unidades" y no se toma como requisito (P-01).

## **11\. Supuestos y preguntas abiertas**

Hay 20 preguntas abiertas; P-04 (facturas sin CAE y anulaciones), P-16 (cambio de categoría por edad) y P-17 (baja del titular) cambian el modelo de datos y conviene responderlas antes de programar Caja y Afiliados.

### **Supuestos**

| ID | Supuesto | Casos de uso |
| :---- | :---- | :---- |
| S-01 | El enlace "Demo: volver todas las claves a su nombre" del ingreso no va en el sistema instalado. | CU-01 |
| S-02 | La facturación del mes es atómica: se emiten todas las facturas o ninguna. | CU-26 |
| S-03 | "Volver a cargar el padrón del Excel" no se habilita en producción; los datos iniciales se cargan una vez con una migración. | CU-51 |
| S-04 | El CAE se pide a ARCA en segundo plano después de emitir la factura; la maqueta lo simula al emitir. | CU-26, CU-27 |
| S-05 | Los mails que la maqueta marca como enviados se envían de verdad desde sistema@integraldeleste.com.ar, con el PDF adjunto. | CU-19, CU-28, CU-30, CU-36 |
| S-06 | Las validaciones de rol, secciones y permisos que la maqueta hace en la pantalla se repiten en la API. | Todos |
| S-07 | El historial de cambios se guarda completo; la maqueta conserva solo los últimos 1000 registros. | CU-52 |

### **Preguntas abiertas**

| ID | Pregunta | Casos de uso |
| :---- | :---- | :---- |
| P-01 | La maqueta conserva código de una pantalla anterior para facturar prestaciones con factura o recibo y "descuento adicional" (reemplazada en la v49). ¿Se confirma que Integral no factura prestaciones? Si es así, el permiso "Aplicar un descuento adicional" no tiene uso. | CU-04, CU-24, CU-47 |
| P-02 | Ningún usuario de atención llega a la ficha del afiliado, así que el permiso "Modificar datos de afiliados" nunca se usa. ¿Los usuarios deben poder corregir datos (por ejemplo el teléfono) desde alguna pantalla? | CU-04, CU-08 |
| P-03 | ¿Alcanza con claves de 4 caracteres? ¿Se obliga a cambiar la clave inicial en el primer ingreso? | CU-03, CU-39 |
| P-04 | ¿Se puede cobrar una factura que todavía no tiene CAE? ¿Cómo se anula una factura emitida? La maqueta no tiene notas de crédito. | CU-26, CU-27, CU-30 |
| P-05 | El débito automático de Mercado Pago se registra a mano. ¿Se mantiene así o se prevé una integración? | CU-29 |
| P-06 | No hay pagos parciales, adelantados ni a cuenta: un recibo cancela una factura entera. ¿Es suficiente? | CU-30, CU-32 |
| P-07 | En reglas "hasta" (por ejemplo óptica, laboratorio), la unidad registra siempre el porcentaje máximo. ¿La unidad debería poder cargar el porcentaje que aplicó? | CU-22, CU-42 |
| P-08 | Al dar de alta a alguien con el DNI de un afiliado dado de baja, la maqueta crea un afiliado nuevo con otro N°. ¿Debería ofrecer reactivarlo? Y al reactivar, ¿se conserva la antigüedad o se reinician las carencias? | CU-06, CU-11 |
| P-09 | En retención, pasar de Integral a Salud (plan individual) a un grupo con familiares: ¿qué pasa con los familiares? | CU-12 |
| P-10 | El "beneficio de regalo" que acepta un afiliado en retención se coordina a mano con la unidad. ¿Debe quedar como un cupo extra que la unidad ve al verificar? | CU-12, CU-22 |
| P-11 | La contabilidad de la maqueta calcula los asientos al mostrarlos y no tiene cierres. ¿Alcanza como vista de gestión o tiene que guardar asientos y cerrar períodos para el contador? | CU-34 |
| P-12 | Deshacer un cambio de precios no revierte las cuotas de afiliados ya actualizadas con "aplicar a actuales". ¿Es aceptable? | CU-45, CU-49 |
| P-13 | En producción, ¿se mantienen la descarga manual de una copia completa y la restauración desde la pantalla? | CU-50 |
| P-14 | ¿Hace falta alguna forma de volver a cargar el padrón en producción? | CU-51 |
| P-15 | La maqueta deja facturar meses futuros sin límite. ¿Se limita al mes en curso y al siguiente? | CU-26 |
| P-16 | La categoría (menor de 18, 18 a 49, 50 a 64, \+64) se elige a mano en el alta. Con la fecha de nacimiento cargada, ¿el sistema debe cambiar la categoría y la cuota al cumplir 18, 50 o 64 años? | CU-06, CU-26 |
| P-17 | Si se da de baja al titular, ¿quién pasa a pagar por el grupo? La maqueta toma como titular al primer integrante del grupo. | CU-10, CU-26 |
| P-18 | La edad máxima de adhesión al sepelio (74 años, 76 con certificado) es informativa. ¿El alta la debe validar? | CU-06, CU-16 |
| P-19 | ¿Cuánto tiempo se conservan los datos de afiliados dados de baja, consultas de interesados y el historial? | CU-10, CU-17, CU-52 |
| P-20 | Hay dos carencias: la general por tipo de prestación (Configuración) y la de cada regla del plan; la maqueta usa la mayor. ¿Se mantienen las dos? | CU-20, CU-42, CU-46 |

