# Integral del Este — Factura electrónica con ARCA (WSFEv1)

El worker pide el CAE de cada factura de cuota al web service **WSFEv1** de ARCA. Primero obtiene un ticket de acceso en **WSAA**, firmado con el certificado de Grupo del Este Salud S.A.S.

| `ARCA_MODO` | Qué hace |
|---|---|
| `simulado` | No llama a ARCA: genera un CAE de prueba (como la maqueta). Para demo y pruebas. |
| `homologacion` | Usa los servidores de prueba de ARCA (`wsaahomo`, `wswhomo`). Los CAE no tienen validez fiscal. |
| `produccion` | Usa los servidores reales. |

## 1. Generar la clave y el pedido de certificado

En una PC segura (no hace falta hacerlo en el VPS):

```bash
openssl genrsa -out arca.key 2048
openssl req -new -key arca.key -subj "/C=AR/O=Grupo del Este Salud S.A.S./CN=integral/serialNumber=CUIT 30716447495" -out arca.csr
```

`arca.key` es privado: va solo a la bóveda y al servidor.

## 2. Certificado de homologación

1. Ingresar a ARCA con la clave fiscal de la empresa y adherir el servicio **"WSASS – Autogestión Certificados Homologación"**.
2. En WSASS: **Nuevo certificado**, pegar el contenido de `arca.csr` y guardar el certificado que devuelve como `arca.crt`.
3. En WSASS: **Crear autorización a servicio** para el alias creado y el servicio **wsfe**.
4. Copiar `arca.crt` y `arca.key` a `/opt/integral/deploy/secrets/` (chmod 600).
5. En `deploy/.env`: `ARCA_MODO=homologacion` y `ARCA_CUIT=30716447495`.
6. Reiniciar el worker: `docker compose up -d worker`.

## 3. Prueba en homologación

1. En **Configuración → Puntos de venta**, cargar el punto de venta (en homologación cualquiera vale, por ejemplo 1) y el último número **0**.
2. En **Caja**, facturar un mes.
3. En unos segundos la columna **CAE** de Facturas se completa.

Si algo falla:

- `docker compose logs worker` muestra el error.
- Los rechazos (por ejemplo "10016 · ImpTotal inválido") aparecen como aviso en rojo y en la factura ("CAE rechazado").

## 4. Producción

1. En ARCA (con clave fiscal): **Administración de Certificados Digitales** → agregar alias → subir un CSR nuevo (generar otra clave para producción) → descargar el certificado.
2. **Administrador de Relaciones de Clave Fiscal** → nueva relación → servicio **"Facturación Electrónica" (wsfe)** → representante: el alias del certificado.
3. **Puntos de venta:** dar de alta un punto de venta del tipo **"RECE para aplicativo y web services"** para las cuotas (CU-46).
4. Reemplazar `secrets/arca.crt` y `secrets/arca.key` por los de producción.
5. En `deploy/.env`, cambiar `ARCA_MODO=produccion` y reiniciar el worker.
6. En **Configuración → Puntos de venta**, cargar el número del punto de venta y el **último número de Factura B y A** que ARCA tiene autorizados en ese punto (si es nuevo, 0).

## Qué se manda a ARCA

| Campo | Valor |
|---|---|
| Comprobante | Factura A (1) a Responsables Inscriptos; Factura B (6) al resto |
| Concepto | 2 · Servicios, con período desde y hasta (el mes facturado) y vencimiento del pago |
| Receptor | B a consumidor final: DNI (96). A o B con CUIT: CUIT (80). Sin documento: 99 |
| Condición frente al IVA del receptor (RG 5616) | CF 5 · RI 1 · Monotributo 6 · Exento 4 |
| Importes | Total; neto e IVA calculados con la alícuota del plan (10,5 % → Id 4). Exento: va como operación exenta |

## Reglas que cumple el worker (CU-27)

- Pide los CAE en **orden de número**, por letra y punto de venta.
- Antes de pedir, consulta **FECompUltimoAutorizado**. Si el número local es el siguiente, pide el CAE:
  - Si el número ya fue autorizado en ARCA (por ejemplo, después de restaurar una copia), trae el CAE con **FECompConsultar**.
  - Si hay un salto en la numeración, **no factura** y avisa a los administradores.
- Si ARCA no responde, reintenta con espera creciente (30 s, 1, 2, 4… minutos). La factura queda "pendiente de CAE" y **se puede cobrar igual** (P-04, S-07). No se envía por mail hasta tener CAE.
- Si ARCA rechaza, marca la factura con el error y avisa. Las siguientes de esa letra esperan hasta corregirlo.
- Revisa todos los días el vencimiento del certificado y avisa 30 días antes.

## Anular una factura

El sistema no emite notas de crédito (P-04, pendiente de definir). Mientras tanto, una factura con error se anula con una nota de crédito hecha en el sitio de ARCA ("Comprobantes en línea"). La factura del sistema se deja pendiente o se registra el ajuste con el contador.
