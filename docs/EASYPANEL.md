# Integral del Este — Despliegue en un VPS con EasyPanel

Guía para instalar el sistema en un VPS que ya tiene **EasyPanel**, tomando el código directamente del repositorio de GitHub. Si el VPS está limpio y no tiene EasyPanel, seguí [INSTALACION.md](INSTALACION.md) (Docker Compose con Caddy).

EasyPanel ya trae su propio proxy (Traefik) con HTTPS automático, así que **no** se usa `deploy/docker-compose.yml` ni Caddy. El sistema se arma con cuatro servicios dentro de un proyecto:

| Servicio | Tipo en EasyPanel | Qué hace |
|---|---|---|
| `db` | Postgres | Base de datos PostgreSQL 17. No se publica a Internet |
| `worker` | App (desde GitHub) | Al arrancar, aplica las migraciones de la base. Después pide los CAE a ARCA y envía los mails |
| `app` | App (desde GitHub) | La aplicación web. Es el único servicio con dominio |
| `copias` | App (desde GitHub) | Copia cifrada de la base cada 6 horas a un bucket externo (restic) |

`app` y `worker` usan la misma imagen (`deploy/Dockerfile`).

> Los nombres de las pestañas y botones de EasyPanel pueden variar un poco según la versión. Si algo no aparece donde dice la guía, buscá el equivalente en la configuración del servicio.

Tiempo estimado: 45 a 90 minutos. Cada servicio construye su imagen la primera vez (5 a 10 minutos).

---

## 0. Antes de empezar

- [ ] **VPS:** EasyPanel instalado y actualizado. Como mínimo 4 GB de RAM; se recomiendan 8 GB.
- [ ] **Dominio:** un subdominio para el sistema, por ejemplo `sistema.integraldeleste.com.ar`, con un registro **A** apuntando a la IP del VPS. Comprobarlo con `dig +short sistema.integraldeleste.com.ar`.
- [ ] **Acceso a EasyPanel** protegido con una clave fuerte y verificación en dos pasos. En este esquema las claves del sistema quedan guardadas en EasyPanel.
- [ ] **Correo:** una cuenta SMTP para `sistema@integraldeleste.com.ar` (host, puerto 587, usuario y clave). Configurar SPF, DKIM y DMARC del dominio, como en INSTALACION.md, paso 10.
- [ ] **Copias:** un bucket S3 compatible en **otro** proveedor (Backblaze B2 o Cloudflare R2), con una clave de acceso solo para ese bucket.
- [ ] **ARCA** (para facturar de verdad): el certificado y la clave privada (`arca.crt`, `arca.key`), según [ARCA.md](ARCA.md). Se puede empezar en modo `simulado` y agregarlos después.

## 1. Preparar el repositorio en GitHub

1. **Rama a desplegar.** El código está en la rama `claude/stoic-ptolemy-6eomzl`. Lo recomendado es unirla a `main` con un pull request y desplegar siempre `main`. Si preferís, en el paso 4 se puede elegir esa rama directamente.
2. **Acceso de EasyPanel al repositorio** (el repositorio es privado):
   1. En GitHub: **Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token**.
   2. Repository access: solo `alebenaben/LaboratorioEste`. Permisos: **Contents: Read-only** y **Metadata: Read-only**. Si querés que GitHub avise a EasyPanel en cada cambio (paso 8), agregá **Webhooks: Read and write**.
   3. En EasyPanel: **Settings → GitHub**, pegar el token y guardar.

## 2. Generar las claves

Generá cada clave con este comando, en tu PC o en la consola del VPS:

```bash
openssl rand -base64 32 | tr -d '/+=' | cut -c1-32
```

Hacen falta cuatro:

| Nombre en esta guía | Para qué |
|---|---|
| `CLAVE_DUENIO` | Usuario `integral`, dueño de la base. Solo la usan las migraciones |
| `CLAVE_APP` | Rol `integral_app`: con él se conectan la aplicación y el worker |
| `CLAVE_COPIAS` | Rol `integral_backup`, de solo lectura, para las copias |
| `CLAVE_RESTIC` | Cifra las copias. **Si se pierde, las copias no se pueden leer** |

Guardalas en la bóveda del equipo (Bitwarden, 1Password o similar) antes de seguir.

## 3. Crear el proyecto y la base de datos

1. En EasyPanel: **Create Project**, con el nombre `integral`.
2. Dentro del proyecto: **+ Service → Postgres**.
   - **Service name:** `db`
   - **Database name:** `integral`
   - **User:** `integral`
   - **Password:** `CLAVE_DUENIO`
   - **Image:** `postgres:17`
3. Crear el servicio. **No** actives la exposición del puerto a Internet.
4. En la pestaña de credenciales del servicio, anotá el **host interno**. Con estos nombres es `integral_db` (formato `<proyecto>_<servicio>`).

## 4. Servicio `worker` (crea la base; va primero)

1. **+ Service → App**, con el nombre `worker`.
2. **Source → GitHub:**
   - **Owner:** `alebenaben`
   - **Repository:** `LaboratorioEste`
   - **Branch:** `main`, o `claude/stoic-ptolemy-6eomzl`
   - **Build path:** `/`
3. **Build → Dockerfile**, con **File:** `deploy/Dockerfile`.
4. **Environment:** pegar este bloque, reemplazando los valores entre `<…>`:

   ```ini
   SERVICIO=worker
   APP_ENV=produccion
   TZ=America/Argentina/Buenos_Aires

   DB_HOST=integral_db
   DB_PORT=5432
   DB_NAME=integral
   DB_USER=integral_app
   DB_PASSWORD=<CLAVE_APP>

   # Solo para las migraciones (crean los roles integral_app e integral_backup)
   DB_OWNER_USER=integral
   DB_OWNER_PASSWORD=<CLAVE_DUENIO>
   DB_BACKUP_PASSWORD=<CLAVE_COPIAS>

   # ARCA: simulado | homologacion | produccion
   ARCA_MODO=simulado
   ARCA_CUIT=30716447495
   ARCA_CERT_FILE=/run/secrets/arca_cert
   ARCA_KEY_FILE=/run/secrets/arca_key

   # Mail: smtp | archivo | desactivado
   MAIL_MODO=smtp
   MAIL_FROM=sistema@integraldeleste.com.ar
   MAIL_FROM_NAME=Integral del Este
   MAIL_REPLY_TO=administracion@integraldeleste.com.ar
   SMTP_HOST=<smtp.proveedor.com>
   SMTP_PORT=587
   SMTP_USER=sistema@integraldeleste.com.ar
   SMTP_PASSWORD=<clave SMTP>

   # Opcional: URL de Healthchecks.io; el worker avisa cada 5 minutos
   HEARTBEAT_WORKER_URL=
   ```

   `SERVICIO=worker` es obligatorio. Sin esa variable, el chequeo de salud busca un servidor web que el worker no tiene, y EasyPanel lo reinicia sin parar.
5. **Mounts → File Mount** (solo con `ARCA_MODO` en `homologacion` o `produccion`):
   - **Mount path** `/run/secrets/arca_cert`, contenido: todo `arca.crt`, incluidas las líneas `-----BEGIN…` y `-----END…`.
   - **Mount path** `/run/secrets/arca_key`, contenido: todo `arca.key`.
6. **Deploy → Command:**

   ```
   sh bin/iniciar-worker.sh
   ```

   Primero aplica las migraciones (`php bin/migrar.php`) y después arranca el worker. Si una migración falla, el servicio no arranca y el error queda en el log.
7. **Deploy.** En **Logs**, el primer arranque tiene que mostrar:

   ```
   Aplicando 001_roles.sql… listo
   Aplicando 002_esquema.sql… listo
   Aplicando 003_vistas.sql… listo
   Aplicando 010_catalogos.sql… listo
   Aplicando 011_padron_inicial.sql… listo
   Se aplicaron 5 script(s).
   Roles y permisos al día.
   {"nivel":"info","msg":"worker iniciado","arca":"simulado","mail":"smtp"}
   ```

   En los arranques siguientes dice "La base ya estaba al día." y "Roles y permisos al día."

## 5. Servicio `app` (la aplicación web)

1. **+ Service → App**, con el nombre `app`.
2. **Source** y **Build:** igual que el worker (mismo repositorio, rama, Build path `/` y Dockerfile `deploy/Dockerfile`).
3. **Environment.** La aplicación **no** lleva la clave del dueño ni la de SMTP: solo encola los mails y el worker los envía.

   ```ini
   APP_ENV=produccion
   TZ=America/Argentina/Buenos_Aires

   DB_HOST=integral_db
   DB_PORT=5432
   DB_NAME=integral
   DB_USER=integral_app
   DB_PASSWORD=<CLAVE_APP>

   SESSION_IDLE_MINUTES=15
   COOKIE_SECURE=1
   TRUST_PROXY=1

   ARCA_MODO=simulado
   ARCA_CUIT=30716447495
   MAIL_MODO=smtp
   MAIL_FROM=sistema@integraldeleste.com.ar
   MAIL_FROM_NAME=Integral del Este
   ```

   `ARCA_MODO` y `MAIL_MODO` tienen que coincidir con los del worker.
4. **Deploy → Command:** dejarlo vacío (usa Apache, el comando de la imagen).
5. **Domains → Add Domain:**
   - **Host:** `sistema.integraldeleste.com.ar`
   - **HTTPS:** activado
   - **Path:** `/`
   - **Port:** `80`
6. **Deploy.** Esperar a que el servicio quede en verde (healthy).
7. Comprobar que `https://sistema.integraldeleste.com.ar/salud` responde **ok**. La primera vez el certificado de Let's Encrypt puede tardar uno o dos minutos.

## 6. Primer ingreso

1. Abrir `https://sistema.integraldeleste.com.ar` e ingresar como **Santiago**, con clave **Santiago**. El sistema obliga a cambiar la clave antes de seguir; cada usuario hace lo mismo en su primer ingreso.
2. En **Configuración**:
   - **Usuarios:** revisar la lista y desactivar los que no correspondan.
   - **Facturación / Puntos de venta:** punto de venta de cuotas y **último N°** de Factura A y B autorizado en ARCA (0 si el punto de venta es nuevo).
   - **Mails:** revisar los textos.
3. Probar el envío: cargar tu propio email en un grupo de prueba y enviarle su factura o una promoción. El mail lo manda el worker en pocos segundos; los errores aparecen en sus **Logs**.

## 7. Servicio `copias`

1. **+ Service → App**, con el nombre `copias`.
2. **Source → GitHub:** mismo repositorio y rama, con **Build path:** `/deploy/backup`.
3. **Build → Dockerfile**, con **File:** `Dockerfile`.
4. **Environment:**

   ```ini
   TZ=America/Argentina/Buenos_Aires
   DB_HOST=integral_db
   DB_NAME=integral
   DB_USER=integral_backup
   DB_PASSWORD=<CLAVE_COPIAS>

   RESTIC_REPOSITORY=s3:https://s3.us-west-004.backblazeb2.com/integral-copias/integral
   RESTIC_PASSWORD=<CLAVE_RESTIC>
   RESTIC_CACHE_DIR=/cache
   AWS_ACCESS_KEY_ID=<id de la clave del bucket>
   AWS_SECRET_ACCESS_KEY=<clave del bucket>

   BACKUP_HORAS=0,6,12,18
   BACKUP_RETENTION=--keep-last 4 --keep-daily 14 --keep-weekly 8 --keep-monthly 24
   HEARTBEAT_BACKUP_URL=
   ```

5. **Mounts → Volume Mount:** nombre `restic_cache`, mount path `/cache`. Es opcional, pero acelera las copias.
6. **Deploy.** El log tiene que mostrar `Servicio de copias iniciado (horas: 0,6,12,18)`.
7. **Primera copia, a mano:** abrir la **Console** del servicio y ejecutar:

   ```bash
   /backup.sh
   ```

   Tiene que terminar con `Copia correcta: …`. En el sistema, **Configuración → Copias de seguridad** muestra la copia en verde.

## 8. Actualizaciones automáticas

En `app` y en `worker`, en **Deployments**, activar **Auto Deploy**, o copiar la **Deploy Webhook URL** y cargarla en GitHub: **Settings → Webhooks → Add webhook**, evento *push*.

Cada vez que se sube código a la rama elegida:

1. EasyPanel vuelve a construir la imagen.
2. El worker aplica las migraciones nuevas al arrancar. Las migraciones son siempre compatibles hacia atrás (ver OPERACION.md), así que la aplicación puede reiniciarse al mismo tiempo.

Antes de una versión importante, hacé una copia a mano (paso 7.7).

## 9. Activar ARCA

1. Seguir [ARCA.md](ARCA.md) para obtener el certificado de **homologación**.
2. En el servicio `worker`:
   - cargar los dos **File Mounts** (paso 4.5);
   - cambiar `ARCA_MODO=homologacion`;
   - **Deploy**.
3. En el servicio `app`, poner el mismo `ARCA_MODO` y **Deploy**.
4. Facturar un mes de prueba y ver que se complete la columna **CAE**.
5. Para **producción**: reemplazar el contenido de los File Mounts por el certificado de producción, poner `ARCA_MODO=produccion` en los dos servicios y cargar los últimos números en **Configuración → Puntos de venta**.

## 10. Tareas de todos los días

| Para qué | Dónde |
|---|---|
| Ver errores de la aplicación | `app` → **Logs** |
| Ver los CAE y los mails | `worker` → **Logs** |
| Estado de las migraciones | `worker` → **Console**: `php bin/migrar.php --estado` |
| Recuperar el acceso de un administrador | `worker` → **Console**: `php bin/crear-admin.php Santiago 'ClaveNueva'` |
| Procesar la cola a mano | `worker` → **Console**: `php bin/worker.php --una-vez` |
| Copia en el momento | `copias` → **Console**: `/backup.sh` |
| Listar las copias | `copias` → **Console**: `restic snapshots --tag pg` |
| Entrar a la base | `db` → **Console**: `psql -U integral -d integral` |
| Reiniciar un servicio | Botón **Restart** del servicio |
| Cambiar la clave de `integral_app` | Nueva `DB_PASSWORD` en `worker` y en `app`; **Deploy** del worker (el migrador pone la clave nueva en cada arranque) y después de `app` |

### Restaurar una copia

Es el mismo procedimiento que la sección A de [OPERACION.md](OPERACION.md), con estas diferencias:

1. Detener `app` y `worker` (botón **Stop**).
2. En la **Console** de `copias`, restaurar en una base nueva, conectándose como el dueño:

   ```bash
   restic snapshots --tag pg
   ```

   ```bash
   PGPASSWORD='<CLAVE_DUENIO>' createdb -h integral_db -U integral integral_restaurada
   ```

   Reemplazá `<id>` por el de la copia elegida:

   ```bash
   restic dump <id> integral.dump | PGPASSWORD='<CLAVE_DUENIO>' pg_restore -h integral_db -U integral -d integral_restaurada --no-owner --role=integral
   ```

3. Comprobar los totales e intercambiar las bases (pasos A.5 y A.6 de OPERACION.md), desde la **Console** de `db`.
4. Iniciar `worker`: aplica las migraciones pendientes y vuelve a crear los roles y sus permisos. Después iniciar `app`.
5. Conciliar la numeración con ARCA (paso A.8 de OPERACION.md).

## 11. Ambiente de pruebas (opcional)

Para probar sin tocar producción, armá un segundo proyecto, por ejemplo `integral-demo`, con los mismos pasos 3 a 5 y estos cambios:

- `DB_HOST=integral-demo_db`
- `APP_ENV=demo`, `ARCA_MODO=simulado`, `MAIL_MODO=archivo` (en los dos servicios)
- otro subdominio, por ejemplo `demo.integraldeleste.com.ar`

Para cargar los **lotes de prueba**, en la **Console** del `worker` del proyecto demo:

```bash
php bin/migrar.php --reiniciar --lotes
```

Después de cargar los lotes, la clave de cada usuario es su nombre. La matriz de pruebas está en [PLAN_DE_PRUEBAS.md](PLAN_DE_PRUEBAS.md). Con `MAIL_MODO=archivo`, los mails quedan en `storage/mails/` dentro del contenedor del worker.

> `--reiniciar` borra todos los datos. Se niega a correr con `APP_ENV=produccion`.

## 12. Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| El dominio da **502 / Bad Gateway** | El puerto del dominio no es 80, o la app todavía no arrancó | Domains → Port `80`; esperar a que el servicio esté en verde |
| `No se pudo conectar a la base` | `DB_HOST` mal escrito | Usar el host interno del servicio Postgres (`integral_db`) |
| `password authentication failed for user "integral_app"` | `DB_PASSWORD` distinta en `app` y en `worker`, o el worker todavía no corrió las migraciones | La misma `CLAVE_APP` en los dos servicios; desplegar primero el worker |
| El worker se reinicia sin parar | Falta `SERVICIO=worker`, o falló una migración | Agregar la variable; ver el error en Logs |
| El ingreso vuelve siempre a la pantalla de ingreso | El dominio está sin HTTPS y `COOKIE_SECURE=1` | Activar HTTPS en Domains |
| `No se encuentran el certificado o la clave de ARCA` | Faltan los File Mounts en el worker | Paso 4.5 |
| Configuración → Copias en rojo | `copias` no corre, o falla el bucket | Logs de `copias`; correr `/backup.sh` a mano |

## Diferencias con la instalación con Docker Compose

- **Proxy:** Traefik (EasyPanel) reemplaza a Caddy. La IP real de cada usuario se sigue usando para el límite de intentos de ingreso: Apache confía en la red interna de Docker (`RemoteIPInternalProxy 10.0.0.0/8`).
- **Red de la base:** el puerto no se publica, pero la red la comparten todos los servicios del proyecto. En Compose estaba aislada (`internal`).
- **Claves:** se guardan como variables de EasyPanel y no como archivos de secretos. Por eso el acceso al panel tiene que estar bien protegido (paso 0).
- **Migraciones:** las corre el worker al arrancar (`bin/iniciar-worker.sh`), en lugar del servicio `migrar` de un solo uso.
