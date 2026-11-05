# Integral del Este — Instalación en un VPS de Hostinger (Ubuntu + Docker + PostgreSQL)

Esta guía deja el sistema funcionando en un VPS de Hostinger con Ubuntu 24.04 LTS, usando Docker Compose como propone el documento de Arquitectura. El sistema queda así:

- **caddy:** recibe Internet por HTTPS, con el certificado de Let's Encrypt automático.
- **app:** la aplicación PHP 8.3 sobre Apache.
- **worker:** pide el CAE a ARCA y envía los mails.
- **db:** PostgreSQL 17.
- **backup:** copias cifradas con restic a un bucket externo, cada 6 horas.

Tiempo estimado: 1 a 2 horas la primera vez.

> **¿El VPS tiene EasyPanel?** Entonces no sigas esta guía: usá [EASYPANEL.md](EASYPANEL.md). EasyPanel ya ocupa los puertos 80 y 443 con su propio proxy.

> En el texto, `sistema.integraldeleste.com.ar` es el dominio de ejemplo (S-01). Reemplazalo por el real.

---

## 1. Antes de empezar

| Qué | Para qué | Dónde se consigue |
|---|---|---|
| VPS **KVM 2** (2 vCPU, 8 GB RAM) con **Ubuntu 24.04** | Servidor | hpanel.hostinger.com → VPS |
| Acceso al **DNS** del dominio | Apuntar `sistema.integraldeleste.com.ar` al VPS | Donde esté registrado el dominio |
| Una **clave SSH** ed25519 por persona que administre | Entrar al servidor | `ssh-keygen -t ed25519` en tu PC |
| Cuenta en un **proveedor SMTP** transaccional (Brevo, Mailgun, Amazon SES, etc.) | Enviar facturas y recibos desde `sistema@integraldeleste.com.ar` | El proveedor elegido (P-02) |
| **Bucket S3** en otro proveedor, con bloqueo de objetos (*Object Lock*) de 30 días | Copias de seguridad fuera del VPS | Backblaze B2 o Cloudflare R2 |
| **Certificado digital de ARCA** y punto de venta "Web Services" | Factura electrónica (CAE) | Ver [docs/ARCA.md](ARCA.md) (P-03) |
| Monitor externo (UptimeRobot, Better Stack o Healthchecks.io) | Avisos si el sistema se cae | Planes gratuitos |

## 2. Crear el VPS en Hostinger

1. En **hPanel → VPS**, elegí el plan **KVM 2** y el sistema operativo **Ubuntu 24.04 LTS** (plantilla limpia, sin panel).
2. Elegí el centro de datos (P-13: si queda fuera de Argentina, revisalo con un asesor por la Ley 25.326).
3. En **SSH keys**, pegá tu clave pública (`~/.ssh/id_ed25519.pub`). Anotá la **IP pública** del VPS.
4. En **VPS → Seguridad → Firewall**, creá una regla que permita solo:
   - TCP 22 (o el puerto SSH que elijas; si la oficina tiene IP fija, limitala a esa IP, P-07),
   - TCP 80 y TCP 443,
   - UDP 443 (HTTP/3).

   Todo lo demás queda en "denegar".
5. Activá el **doble factor** de tu cuenta de Hostinger.

## 3. Apuntar el dominio

En el DNS del dominio creá un registro **A**:

```
sistema.integraldeleste.com.ar.   A   <IP del VPS>
```

(y un **AAAA** si el VPS tiene IPv6). Verificá desde tu PC:

```bash
dig +short sistema.integraldeleste.com.ar
```

## 4. Preparar Ubuntu (como root, una sola vez)

Entrá con `ssh root@<IP>`. Antes de cerrar la sesión de root, abrí una segunda terminal y comprobá que podés entrar como `deploy`.

```bash
# Zona horaria y actualizaciones
timedatectl set-timezone America/Argentina/Buenos_Aires
apt update && apt -y full-upgrade
apt -y install ca-certificates curl gnupg git ufw fail2ban unattended-upgrades

# Usuario de administración (no se trabaja como root)
adduser --disabled-password --gecos "" deploy
mkdir -p /home/deploy/.ssh && cp /root/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh && chmod 700 /home/deploy/.ssh && chmod 600 /home/deploy/.ssh/authorized_keys
usermod -aG sudo deploy
echo "deploy ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/deploy
```

```bash
# SSH solo con clave, sin root
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/; s/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
systemctl restart ssh
```

```bash
# Firewall del VPS (además del de Hostinger)
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw --force enable
```

```bash
# Parches de seguridad automáticos y fail2ban para SSH
dpkg-reconfigure -f noninteractive unattended-upgrades
systemctl enable --now fail2ban
```

## 5. Instalar Docker (repositorio oficial)

```bash
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" > /etc/apt/sources.list.d/docker.list
apt update && apt -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker
usermod -aG docker deploy
```

> Pertenecer al grupo `docker` equivale a ser root: solo para quienes operan el servidor.

Desde acá, todo como `deploy` (`ssh deploy@<IP>`).

## 6. Bajar el sistema

```bash
sudo mkdir -p /opt/integral && sudo chown deploy:deploy /opt/integral
git clone https://github.com/alebenaben/LaboratorioEste.git /opt/integral
cd /opt/integral/deploy
```

## 7. Configuración y secretos

```bash
cp .env.example .env
cp .env.backup.example .env.backup
chmod 600 .env .env.backup
nano .env
```

En `.env` completá:

- **DOMINIO, EMAIL_ACME y PUBLIC_URL:** el dominio y el mail que recibe los avisos de los certificados.
- **ARCA_MODO:** `homologacion` mientras se prueba, `produccion` al salir. Para una demo sin ARCA, `simulado`.
- **ARCA_CUIT:** el CUIT de Grupo del Este Salud S.A.S., sin guiones.
- **SMTP_HOST, SMTP_PORT y SMTP_USER:** los datos del proveedor de mail.
- **HEARTBEAT_WORKER_URL:** opcional, la URL de Healthchecks.io para el worker.

En `.env.backup` completá:

- **RESTIC_REPOSITORY:** el bucket, con el formato `s3:https://<endpoint>/<bucket>/integral`.
- **AWS_ACCESS_KEY_ID:** la clave de acceso del bucket.
- **HEARTBEAT_BACKUP_URL:** el aviso al monitoreo.

Generá los secretos:

```bash
./generar-secretos.sh
```

El script crea `deploy/secrets/` (carpeta 700, archivos 600):

| Archivo | Contenido | Quién lo completa |
|---|---|---|
| `db_password` | Clave del dueño de la base (`integral`) | Generado al azar |
| `db_app_password` | Clave de la aplicación (`integral_app`) | Generado al azar |
| `db_backup_password` | Clave de solo lectura para copias (`integral_backup`) | Generado al azar |
| `restic_password` | Clave de cifrado de las copias. **Sin ella las copias no sirven** | Generado al azar |
| `smtp_password` | Clave del proveedor SMTP | Vos: `nano secrets/smtp_password` |
| `s3_secret_key` | Clave secreta del bucket | Vos |
| `arca.crt` / `arca.key` | Certificado y clave privada de ARCA | Vos (ver [ARCA.md](ARCA.md)) |

**Guardá una copia de todo `secrets/` y de los dos `.env` en la bóveda del equipo** (Bitwarden, 1Password). Con eso y las copias del bucket se reconstruye el servidor.

## 8. Construir y crear la base

```bash
docker compose build
docker compose up -d db
docker compose run --rm migrar
```

El migrador aplica, en orden, los scripts de `database/`:

| Script | Qué hace |
|---|---|
| `001_roles.sql` | Roles `integral_app` y `integral_backup` con sus claves |
| `002_esquema.sql` | Todas las tablas, índices y permisos |
| `003_vistas.sql` | Plan de cuentas, estado de cuenta, asientos y ahorro |
| `010_catalogos.sql` | Planes, precios, prestaciones, reglas, rubros, plantillas de mail y configuración |
| `011_padron_inicial.sql` | Los 24 afiliados del Excel, al día al 31/10/2026, y los usuarios |

Con `docker compose run --rm migrar php bin/migrar.php --estado` ves qué se aplicó.

## 9. Levantar todo

```bash
docker compose up -d
docker compose ps
```

Caddy pide el certificado en el primer acceso. Probá desde tu PC:

```bash
curl -s https://sistema.integraldeleste.com.ar/salud
```

Tiene que responder `{"ok":true,"version":83}`.

## 10. Primer ingreso y claves

- Los usuarios iniciales son los de la maqueta: Santiago, Mauro, Pablo y Katy (administradores); Belén, Cecilia, Angie, Sofía, Mari y CMDE (atención); las unidades Biobárica, Laboratorio y Óptica; y los comercios sanbenito, fitform, leguan, pronoia y barberia.
- La clave inicial de cada uno es **su propio nombre** (por ejemplo `Santiago` / `Santiago`). El sistema **obliga a cambiarla en el primer ingreso** (S-06).
- Entregale a cada persona su usuario por un canal privado (P-06).
- Si nadie puede entrar, se crea o resetea un administrador desde la consola:

  ```bash
  docker compose run --rm migrar php bin/crear-admin.php Santiago 'UnaClaveSegura'
  ```

Después del primer ingreso, como administrador:

1. **Configuración → Puntos de venta:** cargá el punto de venta "Web Services" de las cuotas y el **último número** de Factura B y A emitidos (confirmalos con el contador).
2. **Configuración → Prestaciones:** cargá los valores de referencia, que son la base del cálculo del ahorro.
3. **Configuración → Mails a los afiliados:** revisá los textos.
4. **Usuarios:** dale de baja a quien no corresponda y ajustá qué secciones ve cada uno.

## 11. Mail: SPF, DKIM y DMARC

En el DNS de `integraldeleste.com.ar` agregá los registros que te da el proveedor SMTP:

- **SPF**, por ejemplo: `v=spf1 include:<proveedor> ~all`.
- **DKIM:** el registro TXT o CNAME que indique el proveedor.
- **DMARC**, por ejemplo: `_dmarc  TXT  "v=DMARC1; p=quarantine; rua=mailto:sistemas@integraldeleste.com.ar"`.

Para probar: en **Caja**, abrí una factura con CAE y tocá **"Enviar por mail"** a un grupo con tu email. Los envíos se ven en los logs del worker:

```bash
docker compose logs -f worker
```

## 12. Copias de seguridad

1. En Backblaze B2 o Cloudflare R2, creá el bucket con **versionado y Object Lock de 30 días**, y una clave que solo acceda a ese bucket.
2. Completá `.env.backup` y `secrets/s3_secret_key`.
3. Corré una copia ahora:

   ```bash
   docker compose run --rm backup /backup.sh
   ```

   La primera vez inicializa el repositorio de restic.
4. Verificá en **Configuración → Copias de seguridad** que figure la copia de hoy.

Desde ese momento el servicio `backup` copia a las 00, 06, 12 y 18 h y aplica la retención. La restauración está en [OPERACION.md](OPERACION.md).

## 13. Monitoreo

- **UptimeRobot o Better Stack:** chequeo HTTP cada 5 minutos a `https://<dominio>/salud`, con alerta tras 2 fallas. También vigila el vencimiento del certificado (menos de 14 días).
- **Healthchecks.io:**
  - check "backup", período 6 h, gracia 1 h, con su URL en `HEARTBEAT_BACKUP_URL`;
  - check "worker", período 5 min, gracia 10 min, con su URL en `HEARTBEAT_WORKER_URL`.
- **Disco y memoria:** cron cada 15 minutos (ver [OPERACION.md](OPERACION.md#monitoreo-de-disco-y-memoria)).

## 14. Comprobación final

Desde tu PC:

```bash
nmap -Pn <IP del VPS>
```

Solo tienen que responder 22, 80 y 443. Ni 5432 ni otros puertos.

- [ ] `https://<dominio>/salud` responde `ok`.
- [ ] El certificado es válido (candado en el navegador).
- [ ] Un administrador ingresa, cambia su clave y ve el padrón con los 24 afiliados.
- [ ] Un usuario de atención ve Inicio con sus accesos.
- [ ] Biobárica ingresa y verifica a un afiliado por DNI.
- [ ] Facturación de prueba en **homologación**: las facturas obtienen CAE (columna CAE en Caja).
- [ ] Un mail de factura llega a tu casilla sin caer en spam.
- [ ] Hay una copia de hoy en el bucket y en Configuración → Copias de seguridad.
- [ ] Llegan los avisos del monitoreo.

---

## Anexo A · Ambiente de pruebas en el mismo VPS (recomendado, P-08)

Copiá la carpeta a `/opt/integral-staging`. En `.env` usá:

- `DOMINIO=staging.integraldeleste.com.ar`
- `APP_ENV=demo`
- `ARCA_MODO=homologacion` o `simulado`

Cambiá el nombre del proyecto en `docker-compose.yml` (`name: integral-staging`). Para que los dos Caddy no compitan por los puertos 80 y 443, la forma más simple es servir el sitio de pruebas desde el Caddy de producción: agregá un segundo bloque en el `Caddyfile` que apunte al contenedor `app` del proyecto de pruebas, en una red compartida. Para cargar los datos de prueba:

```bash
docker compose run --rm migrar php bin/migrar.php --lotes
```

## Anexo B · Desarrollo en una PC con Ubuntu (sin Docker)

```bash
sudo apt install php8.3-cli php8.3-pgsql php8.3-intl php8.3-mbstring php8.3-gd php8.3-zip php8.3-xml php8.3-curl postgresql composer
sudo -u postgres psql -c "CREATE ROLE integral LOGIN PASSWORD 'integral' CREATEROLE CREATEDB" -c "CREATE DATABASE integral OWNER integral"
```

Copiá `.env.example` (en la raíz del repositorio) a `.env` y completalo. Después:

```bash
composer install
php bin/migrar.php --lotes
php bin/worker.php --una-vez
php -S 127.0.0.1:8080 -t public
```

`bin/worker.php --una-vez` procesa la cola (CAE simulado y mails a `storage/mails`). Después abrí http://127.0.0.1:8080. Con los lotes cargados, cada usuario entra con su nombre como clave.
