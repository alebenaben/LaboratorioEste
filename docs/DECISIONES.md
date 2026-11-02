# Integral del Este — Decisiones tomadas en el desarrollo

Los documentos de Casos de uso y de Arquitectura dejaron preguntas abiertas (P-xx) y supuestos (S-xx). Esta es la decisión que tomó el desarrollo para cada una. Si administración decide otra cosa, se cambia en una versión siguiente.

## Cambios respecto del documento de Arquitectura

| Tema | Arquitectura | Sistema entregado | Motivo |
|---|---|---|---|
| Lenguaje (ADR-07, P-01) | TypeScript / Node.js | **PHP 8.3 sin framework** + PostgreSQL 17 | Pedido explícito del cliente |
| Frontend | SPA compilada | Páginas generadas en el servidor con los estilos de la maqueta y JavaScript propio (sin dependencias) | Menos piezas; las reglas viven en el servidor |
| Contenedor `app` | Node | PHP 8.3 + Apache (`deploy/Dockerfile`) | Ídem |
| Cola de tareas (ADR-06) | pg-boss | Tabla `cola_tareas` propia, con `FOR UPDATE SKIP LOCKED` | Mismo diseño, sin dependencias |
| PDF (ADR-10) | Navegador para descargar y servidor para los mails | **Siempre en el servidor** (Dompdf), con una sola plantilla por documento | Una plantilla garantiza que el PDF descargado y el del mail sean iguales |
| Usuarios recordados por equipo (CU-01) | — | En el navegador (`localStorage`), solo nombre y etiqueta | Es un dato del equipo, no del servidor |

## Preguntas abiertas de Arquitectura

| ID | Decisión |
|---|---|
| P-01 | PHP; repositorio en GitHub (`alebenaben/LaboratorioEste`). |
| P-02 | Envío por SMTP configurable (`SMTP_*`). El proveedor lo elige el cliente. |
| P-03 | Cliente WSFEv1 real (homologación y producción) más modo simulado. Se puede cobrar sin CAE. |
| P-04 | Copias cada 6 h (RPO 6 h); recuperación objetivo 2 h. |
| P-05 / CU-50 | La pantalla muestra el estado de la última copia automática (rojo a los 7 días) y permite **descargar** una copia completa en JSON (queda registrado). La **restauración no se hace desde la pantalla**: se hace por consola con restic (docs/OPERACION.md), para no tener datos personales restaurables desde una PC. |
| P-06 / S-06 | Clave inicial = nombre del usuario, **cambio obligatorio en el primer ingreso** (no deja hacer nada más). Además, `bin/crear-admin.php` para recuperar el acceso. |
| P-07 | SSH con clave pública y fail2ban; limitar por IP si la oficina tiene IP fija. |
| P-08 | Se documenta el ambiente de pruebas (Anexo A de INSTALACION.md), con `APP_ENV=demo` y lotes de prueba. |
| P-10 | Arranca con el padrón del Excel (24 afiliados al 31/10/2026). |
| P-11 | Alertas en el sistema para administradores y heartbeats para el monitoreo externo. |
| P-12 | Débito automático registrado a mano (CU-29). |

## Preguntas abiertas de Casos de uso

| ID | Decisión |
|---|---|
| P-01 | Integral **no factura prestaciones**: la pantalla vieja no se implementó. El permiso "Aplicar un descuento adicional" se guarda en la configuración, pero no tiene uso. |
| P-02 | Solo los administradores llegan a la ficha. El permiso "Modificar datos de afiliados" igual se valida en la API (CU-04) por si se habilita más adelante. |
| P-03 | Mínimo 4 caracteres; cambio obligatorio de la clave inicial; la clave nueva no puede ser igual al nombre de usuario. Límite de 5 intentos fallidos por usuario y 15 por IP en 15 minutos, con espera creciente. |
| P-04 | Se puede cobrar una factura sin CAE. No hay notas de crédito: se anulan en el sitio de ARCA (docs/ARCA.md). |
| P-05 | Débito automático de a una o todas juntas, con fecha de pago. |
| P-06 | Un recibo cancela una factura entera. "Cobrar N cuotas" emite un recibo por factura. |
| P-07 | En las reglas "hasta" la unidad registra el máximo (como la maqueta). El motor ya acepta un porcentaje menor si se agrega a la pantalla. |
| P-08 | El documento se compara solo contra afiliados **activos**. Al reactivar se conserva el N° y la fecha de alta (y por lo tanto la antigüedad). No se puede reactivar si su documento ya es de otro afiliado activo. |
| P-09 | El cambio de plan en retención **a un plan individual** (Integral → Salud) solo se ofrece si el grupo tiene **un único integrante activo**. Así no quedan familiares en un plan individual. |
| P-10 | El "beneficio de regalo" se registra como aceptado y se coordina a mano con la unidad. |
| P-11 | Los asientos se calculan en una vista de la base (`v_asientos`), sin cierres de período. El libro diario completo sale en Informes. |
| P-12 | Deshacer un cambio de precios **no** revierte las cuotas ya actualizadas con "aplicar a actuales" (como la maqueta). |
| P-13 | Igual que P-05 de Arquitectura. |
| P-14 / S-03 | "Volver a cargar el padrón" solo funciona con `APP_ENV=demo`, con la clave del administrador y el permiso correspondiente. En producción responde 403. |
| P-15 | Se factura desde noviembre 2026 **hasta el mes siguiente al actual** (no meses más adelante). |
| P-16 | La categoría no cambia sola por edad: se cambia en la ficha (propone la cuota del nuevo precio). |
| P-17 | Paga el grupo el **primer integrante activo** (el titular original mientras esté activo). Si el titular se da de baja, el siguiente integrante pasa a recibir las facturas. |
| P-18 | La edad máxima del sepelio sigue siendo informativa. |
| P-19 | No se borra nada automáticamente. Las copias se conservan 24 meses. |
| P-20 | Se mantienen las dos carencias (general por tipo y la de la regla) y rige la mayor. |

## Supuestos confirmados en el código

| ID | Cómo se cumple |
|---|---|
| S-01 | No existe el enlace "Demo: volver todas las claves a su nombre". |
| S-02 | La facturación del mes es una única transacción: si falla una factura, no se emite ninguna. |
| S-04 | El CAE se pide en segundo plano (worker) después de emitir. |
| S-05 | Los mails salen del worker con el PDF adjunto. |
| S-06 | Cada pedido valida rol, sección y permiso en el servidor (403 si no corresponde). |
| S-07 | El historial se guarda completo; el rol de la aplicación no puede modificarlo ni borrarlo. |

## Seguridad aplicada

- **Claves:** Argon2id.
- **Sesión:** del lado del servidor, con cookie HttpOnly, SameSite=Strict y Secure (detrás de HTTPS). Se cierra a los 15 minutos sin actividad, controlado por el servidor.
- **Formularios:** token CSRF en todos los POST.
- **Navegador:** Content-Security-Policy sin scripts en línea.
- **Base de datos:**
  - la aplicación usa el rol `integral_app`, sin permisos para crear ni borrar tablas;
  - las copias usan `integral_backup`, de solo lectura.
- **Autorización de un administrador (CU-04):** token de un solo uso, atado a la acción y al usuario, que vence a los 10 minutos.
