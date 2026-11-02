# Integral del Este — Plan de pruebas

El producto se valida en tres niveles:

| Nivel | Qué cubre | Cómo se corre |
|---|---|---|
| **1. Pruebas automáticas** (PHPUnit, 111 pruebas) | Reglas de negocio (RT-01 a RT-10), criterios de aceptación de los casos de uso, permisos por rol, CSRF, sesión, cliente ARCA con respuestas de ejemplo | `vendor/bin/phpunit` |
| **2. Prueba de humo en el navegador** (Playwright) | Que cada pantalla de cada rol cargue sin errores de JavaScript ni respuestas con error | `node tests/e2e/recorrer.js` |
| **3. Prueba manual de aceptación** | Recorrer los casos de uso con los **lotes de prueba**, según la matriz de la sección 4 | En el ambiente de pruebas (`APP_ENV=demo`) |

Los tres niveles usan los mismos **lotes de prueba** (`database/lotes/`).

## 1. Lotes de prueba

Los lotes se cargan sobre el padrón inicial (24 afiliados, `011_padron_inicial.sql`). Todas las fechas son **relativas al día en que se cargan**: las antigüedades, vencimientos y carencias valen cualquier día que se prueben. Se pueden volver a cargar cuantas veces haga falta (`L00` borra lo cargado antes).

| Lote | Contenido | Casos de uso |
|---|---|---|
| **L00** limpiar | Borra facturas, cobros, usos, autorizaciones, egresos, retenciones, envíos y los afiliados de prueba (N° 500 en adelante) | — |
| **L01** usuarios y padrón | Clave de **cada usuario = su nombre**, sin cambio obligatorio (Santiago/Santiago, Belén/Belén, Biobárica/Biobárica, sanbenito/sanbenito…). Emails y teléfonos de varios grupos; **Santa Cruz Graciela cumple años hoy**; **Goltz Dante** factura como Responsable Inscripto (Factura A) | CU-01, CU-14, CU-26 |
| **L02** cobranza | Facturas del punto de venta de prueba **9**: **Vilar Ivan (N° 68)** deuda vencida hace 30 días; **Santa Cruz Graciela (N° 80)** cuota que vence en 5 días; **Goltz Dante (N° 74)** Factura A pagada | CU-20, CU-30, CU-32, RT-05 |
| **L03** beneficios | Afiliados 501 a 510 con antigüedades medidas desde hoy (ver tabla abajo) y valores de referencia para el ahorro | CU-20 a CU-25, RT-06 |
| **L04** autorizaciones | 2 solicitudes **en auditoría**, 1 autorización de hoy (Belén), 1 rechazo por cuotas impagas (Vilar Ivan). La numeración arranca en 1001 | CU-20, CU-21 |
| **L05** comercios | Gimnasio con máximo 15 % y un consumo de hoy de Julieta Ramallo (75); Barbería (usuario `barberia`) con límite de 2 usos por semestre y **Mauro González (70) con 2 usos** | CU-23, CU-44 |
| **L06** retención | **Godoy Cielo (69)** "lo va a pensar" hace 3 días; **Aguilar Marisa (71)** derivada a administración (pide 10 %); **Gonzalez Angela (72)** insiste en la baja; **Alterini Benjamín (77)** retenido hace 100 días | CU-10, CU-12, CU-13 |
| **L07** egresos | Alquiler fijo del mes pasado (Factura A $ 12.100, IVA $ 2.100), Internet de este mes (Factura B), librería en efectivo | CU-33, CU-34 |
| **L08** promociones | "Llegó el verano" vigente (Óptica); "Mes del corazón" programada (empieza en 10 días, solo Plus); "Invierno saludable" terminada ayer; un envío por WhatsApp ya hecho | CU-18, CU-19 |

Afiliados de prueba de beneficios (L03):

| N° | Situación | Resultado esperado |
|---|---|---|
| 501 | Plus, 7 meses | Hiperbárica **sin cargo**, "le quedan 5 de 5"; todavía sin descuento (desde los 8 meses) |
| 502 (+503 hijo) | Plus, 8 meses; el grupo ya usó 4 sesiones sin cargo | Registrar 2 sesiones: **1 sin cargo y 1 con 50 %** (cupo por grupo) |
| 504 | Integral, 89 días | Óptica **10 %** |
| 505 | Integral, 90 días | Óptica **20 %** |
| 506 | Integral, 400 días, ya usó 6 consultas con 30 % este año | La 7ª consulta va **sin descuento** (tope anual) |
| 507 | Plus, 30 días | **En carencia** |
| 508 | Plus, dado de baja | "Afiliado dado de baja", no se autoriza |
| 509 | Plus, 4 meses | En retención **no** se ofrece el 20 % |
| 510 | Integral, 400 días | El gimnasio no aplica: es un beneficio solo del Plan Plus |

### Cargar los lotes

```bash
# Desarrollo local
php bin/migrar.php --lotes

# Ambiente de pruebas con Docker (APP_ENV=demo)
docker compose run --rm migrar php bin/migrar.php --lotes

# Volver todo a cero y cargar los lotes (solo APP_ENV demo, test o desarrollo)
php bin/migrar.php --reiniciar --lotes
```

> **Nunca en producción.** `--reiniciar` se niega a correr con `APP_ENV=produccion`, y los lotes cambian las claves de los usuarios.

## 2. Pruebas automáticas (PHPUnit)

### Preparación (una sola vez)

1. PHP 8.3 con `pdo_pgsql`, `intl`, `gd`, `zip`; Composer; PostgreSQL 16 o 17.
2. Un rol dueño que pueda crear bases:

   ```bash
   sudo -u postgres psql -c "CREATE ROLE integral LOGIN PASSWORD 'integral' CREATEDB CREATEROLE"
   ```

3. Copiar `.env.example` a `.env` y completar `DB_OWNER_USER`, `DB_OWNER_PASSWORD`, `DB_PASSWORD` y `DB_BACKUP_PASSWORD` (los dos últimos son las claves que el migrador pone a `integral_app` e `integral_backup`).
4. `composer install`

### Correr

```bash
vendor/bin/phpunit
```

`phpunit.xml` fuerza `DB_NAME=integral_test`, `APP_ENV=test`, `ARCA_MODO=simulado` y `MAIL_MODO=desactivado`. En cada corrida, `tests/bootstrap.php`:

1. se niega a seguir si el nombre de la base no termina en `_test` (protege la base real);
2. crea `integral_test` si no existe;
3. la vuelve a crear desde cero con los scripts de `database/` y todos los lotes (`migrar.php --reiniciar --lotes`).

Cada prueba de integración corre dentro de una transacción que se deshace al terminar: las pruebas no dependen del orden ni se ensucian entre sí.

```bash
vendor/bin/phpunit --testsuite Unit          # solo reglas puras (no necesita base)
vendor/bin/phpunit --testsuite Integracion
vendor/bin/phpunit --testsuite Http
vendor/bin/phpunit --filter CajaTest
vendor/bin/phpunit --testdox                 # lista cada prueba con su nombre en castellano
```

Resultado esperado: `OK (111 tests, 566 assertions)`.

| Archivo | Qué prueba |
|---|---|
| `tests/Unit/FmtTest.php` | RT-02 y RT-03: formatos de DNI, CUIT, montos, fechas, celular para WhatsApp (`03435 15 013651` → `5493435013651`), importe en letras, IVA contenido, separación de apellido y nombres |
| `tests/Unit/BeneficiosTest.php` | RT-06 sin base: escala por antigüedad, carencia, sin cargo + descuento, tope de usos, modo "hasta", importación de renglones de una lista de precios |
| `tests/Integracion/AfiliadosTest.php` | CU-05 a CU-14 |
| `tests/Integracion/BeneficiosTest.php` | CU-20 a CU-25 |
| `tests/Integracion/CajaTest.php` | CU-26 a CU-32, worker de CAE y mails, cliente WSFEv1 con respuestas SOAP de ejemplo |
| `tests/Integracion/GestionConfigTest.php` | CU-33 a CU-52 |
| `tests/Http/AccesoHttpTest.php` | CU-01 a CU-04 a través de la aplicación completa: ingreso, límite de intentos, 403 por rol y sección, CSRF, cierre por inactividad, clave inicial obligatoria, `/salud` y que todas las pantallas respondan 200 |

## 3. Prueba de humo en el navegador

Con la aplicación levantada y los lotes cargados:

```bash
npm install playwright && npx playwright install chromium
BASE=http://127.0.0.1:8080 node tests/e2e/recorrer.js capturas/
```

Ingresa como Santiago (administrador), Belén (usuario), Biobárica (unidad) y sanbenito (comercio). Recorre sus pantallas y guarda una captura de cada una en `capturas/`. Termina con error si alguna pantalla tuvo un error de JavaScript o respondió 4xx/5xx. Si alguna clave ya no es igual al nombre, se pasa con `CLAVE_SANTIAGO=…`, `CLAVE_BELÉN=…`, etc.

## 4. Matriz de aceptación manual

Ambiente: el de pruebas (Anexo A de [INSTALACION.md](INSTALACION.md)) o el local, con `ARCA_MODO=simulado`, `MAIL_MODO=archivo` (los mails quedan en `storage/mails/`) y los lotes recién cargados. Para cada fila: hacer los pasos, comparar con el resultado esperado y marcar ✔ o anotar la diferencia.

**Usuarios:** **S** = Santiago (administrador) · **B** = Belén (usuario) · **U** = Biobárica (unidad) · **C** = sanbenito (comercio, gimnasio). Clave = nombre.

### Acceso (CU-01 a CU-04, CU-52)

| CU | Pasos | Resultado esperado | Prueba automática |
|---|---|---|---|
| 01 | Ingresar como `santiago` (minúsculas, sin tilde) | Entra como Santiago | `AccesoHttpTest::testUsuarioSinMayusculasNiTildes` |
| 01 | Ingresar como S, B, U y C | S y B van a Inicio; U y C van directo a "Verificar afiliado" | `testInicioPorRol` |
| 01 | Clave equivocada 5 veces | "Demasiados intentos": espera antes de volver a probar | `testLimiteDeIntentos` |
| 01 | Ingresar como S (hay 2 solicitudes en auditoría de L04) | Aviso de autorizaciones pendientes de auditar | `testAvisoDeAuditoriaAlIngresar` |
| 01 | Como B, escribir la dirección `/config` | "Sin acceso" (403) | `testPermisosPorRol` |
| 02 | Quedarse 15 minutos sin actividad | Cierra la sesión y vuelve a la pantalla de ingreso | `testCierrePorInactividad` |
| 03 | Mi clave: clave nueva de 3 caracteres; después una válida | La primera no se acepta; con la segunda se puede ingresar | `testCambiarClave` |
| 03 | Ingresar con un usuario recién creado | Obliga a cambiar la clave antes de hacer otra cosa | `testClaveInicialObligatoria` |
| 04 | Como B, cambiar la cuota de un afiliado en la ficha | Pide la clave de un administrador; con la de Santiago se guarda y el historial dice "autorizó Santiago" | `GestionConfigTest::testAutorizacionDeAdministrador` |
| 52 | Como S, Configuración → Historial de cambios | Se ven ingresos, altas y cambios con usuario y fecha; como B no aparece | `testHistorialSoloAdministradores`, `testHistorialSoloAgregado` |

### Afiliados (CU-05 a CU-14)

| CU | Pasos | Resultado esperado | Prueba automática |
|---|---|---|---|
| 05 | Buscar `ramallo 75`, `2891` y `RAMALLO` | Encuentra a Julieta Ramallo (75) sin importar mayúsculas, tildes ni puntos | `AfiliadosTest::testBusquedaEnElPadron` |
| 06 | Alta de un grupo de 2 integrantes | Números consecutivos; cuota estimada del grupo = suma de las cuotas | `testAltaConNumerosConsecutivos`, `testCuotaEstimadaDeUnGrupo` |
| 06 | Alta con un documento de un afiliado activo | No se permite | `testDocumentosRepetidos` |
| 06 | Alta en Plan Salud (individual) con un familiar | No se permite | `testPlanIndividualSinFamiliares` |
| 06 | Alta sin email (sin marcar "No tiene email") | Pide el email | `testEmailYPrecio` |
| 07 | Abrir la ficha de Vilar Ivan (68) | Estado "Deuda vencida" en rojo y cuota del grupo | `testCuotaDelGrupoEnLaFicha` |
| 08 | Cambiar el email del grupo y la categoría | Propone la cuota del precio nuevo; el historial guarda antes y después | `testEdicionRegistraAntesYDespues` |
| 08 | Ficha de Goltz Dante (74) | Condición Responsable Inscripto → factura A | `testResponsableInscriptoFacturaA` |
| 09 | Agregar una promoción a la cuota de un grupo | Propone el inicio del mes siguiente | `testInicioPropuestoDeLaPromocion` |
| 10 | Como S, dar de baja a un afiliado | Primero ofrece las propuestas de retención; si sigue, pide el motivo | `testBajaConRetencion` |
| 10 | Dar de baja a Alterini (77) | No ofrece descuentos (ya fue retenido hace 100 días) | `testSinDescuentosSiYaFueRetenido` |
| 11 | Reactivar al afiliado 508 | Conserva el N° y la fecha de alta | `testBajaSoloAdministradorYReactivacion` |
| 12 | Como B, Retención con el afiliado 509 | No ofrece el 20 % (tiene 4 meses) | `testSinVeintePorCientoConPocaAntiguedad` |
| 12 | Como B, un afiliado pide 10 % | Se deriva a administración; Santiago lo ve y lo aplica | `testUsuarioDerivaYAdministradorAplica` |
| 13 | Retención → "Para llamar hoy" | Aparece Godoy Cielo (69); Gonzalez Angela (72) espera la baja de un administrador | `testLoVaAPensar`, `testConfirmarBajaPedida` |
| 14 | Inicio | Franja con Santa Cruz Graciela, que cumple años; botón de saludo por WhatsApp | `testCumpleanios` |

### Comercial (CU-15 a CU-19)

| CU | Pasos | Resultado esperado | Prueba automática |
|---|---|---|---|
| 15 | Cotizador: "Juan Pérez", 2 adultos y 1 menor en Plan Plus | Total del grupo; PDF de la cotización con los datos | `AccesoHttpTest::testPantallasResponden` (pantalla) |
| 16 | Cotizador → beneficios de los planes | Tabla comparativa por rubro, igual a la maqueta | ídem |
| 17 | Enviar información a una persona por WhatsApp | Arma el mensaje y el PDF de información del plan | — (manual) |
| 18 | Promociones | "Llegó el verano" vigente, "Mes del corazón" programada, "Invierno saludable" terminada | `GestionConfigTest::testEstadoDePromociones` |
| 19 | Enviar "Llegó el verano" | Por mail a los grupos con email (en `storage/mails/`); de a uno por WhatsApp al resto; no se repite el envío ya hecho | `testEnvioDePromocion` |

### Beneficios (CU-20 a CU-25)

| CU | Pasos | Resultado esperado | Prueba automática |
|---|---|---|---|
| 20 | Como B, autorizar una prestación a Vilar Ivan (68) | Rechazo por cuotas impagas | `BeneficiosTest::testCuotasImpagas` |
| 20 | Autorizar al 507 y al 508 | "En carencia" y "dado de baja" | `testCarenciaYBaja` |
| 20 | Autorizar hiperbárica al 501 | Sin cargo; N° de autorización correlativo desde 1001; PDF | `testAutorizacionDescuentaDelCupo`, `testNumeracionDeAutorizaciones` |
| 20 | Autorizar una prestación marcada "requiere auditoría" | Queda "En auditoría" | `testRequiereAuditoria` |
| 21 | Como S, Auditoría: aprobar una y rechazar otra | Cambian de estado; la franja roja desaparece cuando no quedan | `testAuditoria` |
| 22 | Como U, verificar al 501 | "Hoy dispone de 5 de 5 sin cargo" | `testHoyDisponeDe` |
| 22 | Como U, registrar 2 sesiones al 502 | 1 sin cargo y 1 con 50 % | `testCupoPorGrupo`, `testRegistrarUsoEnUnidad` |
| 22 | Óptica al 504 y al 505 | 10 % y 20 % | `testEscalaDeOptica` |
| 22 | Consulta al 506 | Sin descuento (tope anual de 6) | `testTopeAnualDeDescuentos` |
| 23 | Ingresar como `barberia` y verificar a Mauro González (70) | Rechazado: ya usó 2 este semestre (límite: 2) | `testLimiteDeUsosDelComercio`, `testLimitePorSemestre` |
| 23 | Como C (gimnasio), consumo de $ 20.000 al 20 % para Julieta Ramallo (75) | Lo baja al máximo del 15 %: ahorro $ 3.000 | `testComercioRespetaElMaximo` |
| 23 | Como C, verificar al 510 (Integral) y un DNI que no es afiliado | "…no incluye este beneficio" y "No es afiliado de Integral del Este." | `testPlanSinElRubro` |
| 24 | Beneficios → Usos en unidades | Lista de usos con importes y ahorro | `testAhorroConValorDeReferencia` |
| 25 | Historial de beneficios del 502 | Sesiones del grupo con su condición | `testAutorizacionComoUso` |

### Caja (CU-26 a CU-32)

| CU | Pasos | Resultado esperado | Prueba automática |
|---|---|---|---|
| 26 | Caja → Facturar el mes | Una factura por grupo activo: **B** para consumidores finales y **A** para Goltz Dante; vencimiento el día configurado | `CajaTest::testLetraYVencimiento` |
| 26 | Facturar el mismo mes otra vez | No genera facturas nuevas | `testFacturarDosVecesNoDuplica` |
| 26 | Configurar último N° B = 120 y facturar | La primera B es la 121 | `testNumeracionDesdeElUltimoConfigurado` |
| 26 | Intentar facturar octubre 2026 | No se puede (se factura desde noviembre 2026) | `testNoFacturaAntesDelInicio` |
| 26 | Dar de alta un afiliado después de facturar | No se agrega a la factura ya emitida | `testAltaPosteriorNoSeFactura` |
| 27 | Esperar unos segundos (worker) | La columna CAE se completa (simulado) | `testCaeYMail`, `testClienteWsfeConRespuestasDeEjemplo`, `testDatosParaArca` |
| 28 | Enviar las facturas del mes por mail | Un mail por grupo con email y su PDF adjunto; al repetir no se reenvía | `testEnvioMasivoNoReenvia` |
| 29 | Débito automático con fecha de pago | Un recibo por factura con esa fecha | `testDebitoConFechaDePago` |
| 30 | Cobrar en mostrador la factura de Santa Cruz Graciela (80); cobrarla otra vez | Un solo recibo; el segundo intento avisa que ya está pagada | `testCobrarDosVeces` |
| 31 | Ver la factura B y el recibo en PDF | Factura con "IVA contenido" (Ley 27.743); recibo con importe en letras y sin el nombre de quien cobró | `testIvaContenidoEnLaFactura`, `testReciboSinQuienCobro` |
| 32 | Cuenta corriente del grupo de Vilar Ivan | Debe, haber y saldo, con la deuda vencida | `testCuentaCorriente` |

### Gestión (CU-33 a CU-38)

| CU | Pasos | Resultado esperado | Prueba automática |
|---|---|---|---|
| 33 | Egresos | "Gastos fijos sin cargar": Alquiler; cargarlo con un clic (Factura A con IVA) | `GestionConfigTest::testGastosFijos`, `testEgresoConIva` |
| 34 | Contabilidad | Asientos de facturas, cobros y egresos; debe = haber | `testContabilidad` |
| 35 | Estadísticas | Paneles con datos; deuda vencida incluye a Vilar Ivan | `testDeudaVencida` |
| 36 | Resumen mensual de beneficios | Vista previa y envío por mail (en `storage/mails/`) | `testResumenMensual` |
| 37 | Resumen de ahorro de un titular | Mismo ahorro que el informe de ahorro | `testAhorroCoincideConElInforme` |
| 38 | Informes: descargar cada uno en Excel y PDF | Los 8 informes abren; la vista previa muestra 60 filas | `testInformes` |

### Configuración (CU-39 a CU-51)

| CU | Pasos | Resultado esperado | Prueba automática |
|---|---|---|---|
| 39 | Crear un usuario rol unidad; desactivarlo | Ingresa con clave = nombre y debe cambiarla; desactivado no puede entrar | `GestionConfigTest::testUsuarios` |
| 40, 45 | Cambiar un precio de prestación y de un plan | Pantalla de confirmación con "antes → después" y alertas; pide la clave | `testConfirmarYDeshacer` |
| 49 | Deshacer el último cambio | Vuelve el valor anterior; solo el último se puede deshacer | `testConfirmarYDeshacer` |
| 45 | Cambiar precios sin "aplicar a actuales" | Las cuotas de los afiliados no cambian | `testPreciosSinAplicarAActuales` |
| 41 | Importar una lista de precios en Excel o PDF | Reconoce los renglones y propone los cambios | `testImportacionDePrecios`, `Unit\BeneficiosTest::testRenglonesDeUnaListaDePrecios` |
| 42, 44 | Cambiar una regla de beneficio o un rubro de comercio | Pasa por la confirmación (C) | `testConfirmarYDeshacer` |
| 43 | Crear una unidad con su usuario | La unidad aparece en verificación | `testUnidades` |
| 46 | Cambiar el punto de venta y el día de vencimiento | Se usan en la próxima facturación | `CajaTest::testNumeracionDesdeElUltimoConfigurado` |
| 47 | Marcar "Modificar cuotas" como restringida | B necesita la clave de un administrador | `AfiliadosTest::testCambioDeCuotaRequiereAdministrador` |
| 48 | Editar el texto del mail de factura | El próximo mail usa el texto nuevo, con los datos reemplazados | `testPlantillasDeMail` |
| 50 | Copias de seguridad | Muestra la última copia (en rojo si tiene más de 7 días); "Descargar copia" baja un JSON | `AccesoHttpTest::testPantallasResponden` |
| 51 | Volver a cargar el padrón (solo con `APP_ENV=demo`) | Pide la clave; deja el padrón inicial. En producción no está disponible | — (manual) |

## 5. Pruebas que se hacen en el VPS

No se pueden hacer sin el servidor y las cuentas reales:

| Prueba | Cómo | Esperado |
|---|---|---|
| Contenedores | `docker compose ps` | Todos `healthy` |
| HTTPS | Abrir `https://<dominio>` | Candado válido; `http://` redirige |
| Mail real | Enviar una factura a una casilla propia | Llega con el PDF y no cae en spam (SPF, DKIM y DMARC configurados) |
| ARCA homologación | [ARCA.md](ARCA.md), sección 3 | La factura obtiene CAE de homologación |
| Copia y restauración | `docker compose run --rm backup /backup.sh` y el procedimiento A de [OPERACION.md](OPERACION.md) | La base restaurada tiene los mismos totales |
| Despliegue | `./deploy.sh <etiqueta>` | `/salud` responde y `deploys.log` lo registra |
