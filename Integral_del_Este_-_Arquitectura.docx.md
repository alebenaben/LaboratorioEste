# **Integral del Este — Arquitectura**

Oct 29, 2026 · @Ale

La propuesta es una aplicación web con API propia y PostgreSQL, en contenedores Docker sobre un único VPS Hostinger KVM2, con Caddy al frente y copias de seguridad cifradas fuera del servidor. Este documento parte de la maqueta "Integral del Este \- demo v82.html", que hoy corre entera en el navegador y guarda los datos en localStorage.

## **1\. Resumen del sistema y objetivos**

Integral del Este es el sistema de gestión de un plan de beneficios de salud de Grupo del Este Salud S.A.S. (Colonia Avellaneda, Entre Ríos). Administra afiliados y grupos familiares, factura y cobra las cuotas, y registra el uso de beneficios en las unidades de negocio y comercios adheridos.

**Qué hace, según la maqueta**

* **Afiliados:** altas con grupo familiar, ficha, modificaciones, bajas con circuito de retención, cumpleaños.  
* **Comercial:** cotizador de planes, promociones en la cuota, campañas por mail o WhatsApp, consultas de interesados.  
* **Beneficios:** reglas por plan (carencias, sesiones sin cargo, descuentos escalonados, topes por período), autorizaciones con auditoría y verificación del afiliado por unidades y comercios.  
* **Administración:** facturación mensual de cuotas (Factura A o B, punto de venta ARCA), débito automático, cobranza en mostrador con recibo, cuentas corrientes, egresos, contabilidad, estadísticas e informes en Excel y PDF.  
* **Control:** usuarios con roles y secciones visibles, acciones que exigen la clave de un administrador, historial de cambios con deshacer, copias de seguridad.

**Qué tiene que cambiar al pasar a servidor**

| Hoy en la maqueta | En el sistema instalado |
| :---- | :---- |
| Datos en el navegador de cada computadora | Una sola base PostgreSQL compartida por todos |
| Permisos y claves controlados en el navegador | Autenticación y permisos aplicados en la API |
| CAE de ARCA simulado | CAE real por el web service de factura electrónica de ARCA |
| Mails simulados (se abre el programa de mail) | Envío automático desde sistema@integraldeleste.com.ar, con el PDF adjunto |
| Copia de seguridad descargada a mano | Copias automáticas, como mínimo diarias, guardadas fuera del VPS |

**Objetivos de la arquitectura**

1. Que los 20 usuarios (5 a la vez) trabajen sobre los mismos datos, desde la PC o el celular, por HTTPS.  
2. Que las reglas de negocio y los permisos vivan en el servidor, no en el navegador.  
3. Que se pueda recuperar el sistema completo en otro VPS a partir de las copias externas.  
4. Que la operación quepa en una persona técnica a tiempo parcial: pocos servicios, todo en un docker-compose.yml.

## **2\. Componentes**

Son cinco contenedores en el VPS y cuatro servicios externos. Solo Caddy recibe tráfico de Internet; la base de datos no tiene ningún puerto publicado.

![][image1]

*Figura 1\. Componentes del sistema: contenedores en el VPS y servicios externos.*

| Servicio | Imagen | Qué hace | Habla con |
| :---- | :---- | :---- | :---- |
| caddy | caddy:2.8 | Termina TLS con certificados automáticos, redirige HTTP a HTTPS, agrega cabeceras de seguridad y pasa las peticiones a app. | Internet, app |
| app | propia (integral-app) | Sirve la SPA compilada y la API REST: login y sesiones, permisos por rol y sección, reglas de beneficios, facturación, caja, informes, historial de cambios. Encola las tareas lentas en la base. | db |
| worker | la misma integral-app, otro comando | Ejecuta la cola: pedido de CAE a ARCA, envío masivo de facturas y recibos por mail, resumen mensual de beneficios, campañas de promociones por mail. Reintenta lo que falla. | db, ARCA, SMTP |
| db | postgres:17 | Base única con todos los datos: afiliados, grupos, facturas, recibos, usos, egresos, usuarios, configuración, historial y cola de tareas. | app, worker, backup |
| backup | propia, sobre postgres:17 \+ restic | Cada 6 horas hace pg\_dump, lo cifra y lo sube al almacenamiento externo; aplica la retención y avisa al monitoreo. | db, S3, monitoreo |

**Servicios externos**

* **ARCA (WSAA \+ WSFEv1):** autoriza cada factura de cuota y devuelve el CAE. Requiere el certificado digital de Grupo del Este Salud S.A.S. para el punto de venta configurado.  
* **Proveedor SMTP transaccional:** envía los mails desde sistema@integraldeleste.com.ar. Hace falta configurar SPF, DKIM y DMARC en el dominio para que no caigan en spam.  
* **Almacenamiento S3 compatible:** guarda las copias cifradas en otro proveedor, otro país o región (por ejemplo Backblaze B2 o Cloudflare R2).  
* **Monitoreo externo:** comprueba desde afuera que el sitio responde y recibe un aviso de cada copia y de cada vuelta del worker; si un aviso no llega a tiempo, alerta.

**WhatsApp** no pasa por el servidor: la maqueta arma un enlace wa.me o comparte el PDF desde el navegador, y así se mantiene. La maqueta menciona como posibilidad futura el envío automático por WhatsApp Business; queda fuera de este alcance.

**SUPUESTO:** los PDF (facturas, recibos, cotizaciones, promociones) se siguen generando en el navegador para descargar o compartir. Para los mails automáticos con adjunto, el worker genera el mismo PDF en el servidor. Las librerías que hoy se cargan desde CDN (jsPDF, SheetJS, pdf.js) se empaquetan dentro de la SPA.

## **3\. Despliegue en el VPS con Docker Compose**

Todo el sistema se define en un docker-compose.yml dentro de /opt/integral y se levanta con docker compose up \-d. El KVM2 (2 vCPU y 8 GB de RAM según la ficha del plan; confirmar al contratar) sobra para 5 usuarios a la vez; los límites de memoria dejan más de 3 GB libres para el sistema operativo y la caché de disco.

**Estructura en el servidor**

/opt/integral/  
  docker-compose.yml  
  Caddyfile  
  .env               \# variables no secretas (chmod 600\)  
  .env.backup        \# destino y retención de copias (chmod 600\)  
  secrets/           \# un archivo por secreto (chmod 700, archivos 600\)  
    db\_password  
    smtp\_password  
    restic\_password  
    s3\_secret\_key  
    arca.crt  
    arca.key  
**docker-compose.yml de referencia**

name: integral

x-log: \&log  
  driver: json-file  
  options: { max-size: "10m", max-file: "5" }

x-app: \&app  
  image: ghcr.io/\<org\>/integral-app:\${APP\_TAG}  
  env\_file: .env  
  restart: unless-stopped  
  depends\_on:  
    db: { condition: service\_healthy }  
  logging: \*log

services:  
  caddy:  
    image: caddy:2.8  
    restart: unless-stopped  
    ports: \["80:80", "443:443", "443:443/udp"\]  
    volumes:  
      \- ./Caddyfile:/etc/caddy/Caddyfile:ro  
      \- caddy\_data:/data  
      \- caddy\_config:/config  
    networks: \[edge\]  
    mem\_limit: 256m  
    logging: \*log

  app:  
    \<\<: \*app  
    command: \["node", "dist/server.js"\]  
    secrets: \[db\_password\]  
    networks: \[edge, db\_net\]  
    healthcheck:  
      test: \["CMD", "wget", "-qO-", "http\://localhost:3000/salud"\]  
      interval: 30s  
      timeout: 5s  
      retries: 3  
    mem\_limit: 1g

  worker:  
    \<\<: \*app  
    command: \["node", "dist/worker.js"\]  
    stop\_grace\_period: 60s  
    secrets: \[db\_password, smtp\_password, arca\_cert, arca\_key\]  
    networks: \[db\_net, egress\]  
    mem\_limit: 768m

  db:  
    image: postgres:17  
    restart: unless-stopped  
    environment:  
      POSTGRES\_DB: integral  
      POSTGRES\_USER: integral  
      POSTGRES\_PASSWORD\_FILE: /run/secrets/db\_password  
    secrets: \[db\_password\]  
    volumes: \[pgdata:/var/lib/postgresql/data\]  
    networks: \[db\_net\]  
    shm\_size: 256m  
    healthcheck:  
      test: \["CMD-SHELL", "pg\_isready \-U integral \-d integral"\]  
      interval: 10s  
      retries: 5  
    mem\_limit: 2g  
    logging: \*log

  backup:  
    image: ghcr.io/\<org\>/integral-backup:\${BACKUP\_TAG}  
    restart: unless-stopped  
    env\_file: .env.backup  
    secrets: \[db\_password, restic\_password, s3\_secret\_key\]  
    volumes: \[backup\_cache:/cache\]  
    networks: \[db\_net, egress\]  
    mem\_limit: 512m  
    logging: \*log

networks:  
  edge: {}  
  db\_net: { internal: true }  
  egress: {}

volumes:  
  pgdata: {}  
  caddy\_data: {}  
  caddy\_config: {}  
  backup\_cache: {}

secrets:  
  db\_password:     { file: ./secrets/db\_password }  
  smtp\_password:   { file: ./secrets/smtp\_password }  
  restic\_password: { file: ./secrets/restic\_password }  
  s3\_secret\_key:   { file: ./secrets/s3\_secret\_key }  
  arca\_cert:       { file: ./secrets/arca.crt }  
  arca\_key:        { file: ./secrets/arca.key }  
**Redes**

| Red | Tipo | Contenedores | Para qué |
| :---- | :---- | :---- | :---- |
| edge | bridge | caddy, app | Caddy le pasa las peticiones a la API. |
| db\_net | bridge internal: true, sin salida a Internet | app, worker, db, backup | Acceso a PostgreSQL. Nada de afuera llega a esta red. |
| egress | bridge | worker, backup | Salida hacia ARCA, el SMTP y el almacenamiento de copias. |

**Volúmenes persistentes**

| Volumen | Montado en | Contenido | ¿Se respalda? |
| :---- | :---- | :---- | :---- |
| pgdata | db:/var/lib/postgresql/data | Toda la base de datos | Sí, con pg\_dump (sección 5), no copiando el volumen |
| caddy\_data | caddy:/data | Certificados TLS y claves de cuenta ACME | No hace falta: Caddy los vuelve a pedir |
| caddy\_config | caddy:/config | Configuración autoguardada de Caddy | No |
| backup\_cache | backup:/cache | Caché de restic para acelerar las subidas | No |

Los archivos de /opt/integral (compose, Caddyfile, .env, secrets/) se guardan aparte, en un gestor de contraseñas o bóveda del equipo, para poder reconstruir el servidor.

**Políticas de reinicio**

* Todos los servicios usan restart: unless-stopped: vuelven solos tras un error o un reinicio del VPS, y quedan detenidos si alguien los paró a mano.  
* app y worker esperan a que db pase su healthcheck antes de arrancar.  
* El servicio Docker se habilita al inicio (systemctl enable docker) para que todo arranque después de un reinicio del VPS.

**Variables de entorno**

| Variable | Servicio | Ejemplo o valor | ¿Secreto? |
| :---- | :---- | :---- | :---- |
| APP\_TAG | app, worker | 1.4.2 (versión fija, nunca latest) | No |
| PUBLIC\_URL | app, worker | https\://sistema.integraldeleste.com.ar (SUPUESTO) | No |
| TZ | todos | America/Argentina/Buenos\_Aires | No |
| DB\_HOST / DB\_NAME / DB\_USER | app, worker | db / integral / integral | No |
| DB\_PASSWORD\_FILE | app, worker, backup | /run/secrets/db\_password | Sí, por archivo |
| SESSION\_IDLE\_MINUTES | app | 15 (igual que la maqueta) | No |
| SMTP\_HOST / SMTP\_PORT / SMTP\_USER | worker | según el proveedor / 587 | No |
| SMTP\_PASSWORD\_FILE | worker | /run/secrets/smtp\_password | Sí, por archivo |
| MAIL\_FROM | worker | sistema@integraldeleste.com.ar | No |
| ARCA\_ENV | worker | homologacion o produccion | No |
| ARCA\_CUIT | worker | 30716447495 | No |
| ARCA\_CERT\_FILE / ARCA\_KEY\_FILE | worker | /run/secrets/arca\_cert / /run/secrets/arca\_key | Sí, por archivo |
| HEARTBEAT\_WORKER\_URL | worker | URL del monitoreo externo | No |
| RESTIC\_REPOSITORY | backup | s3:https\://\<endpoint\>/\<bucket\>/integral | No |
| RESTIC\_PASSWORD\_FILE | backup | /run/secrets/restic\_password | Sí, por archivo |
| AWS\_ACCESS\_KEY\_ID | backup | clave de acceso con permiso solo de escritura en el bucket | No |
| AWS\_SECRET\_ACCESS\_KEY\_FILE | backup | /run/secrets/s3\_secret\_key | Sí, por archivo |
| BACKUP\_SCHEDULE | backup | 0 \*/6 \* \* \* | No |
| BACKUP\_RETENTION | backup | \--keep-last 4 \--keep-daily 14 \--keep-weekly 8 \--keep-monthly 24 | No |
| HEARTBEAT\_BACKUP\_URL | backup | URL del monitoreo externo | No |

## **4\. Reverse proxy y TLS**

Se recomienda **Caddy**: pide y renueva solo los certificados de Let's Encrypt (con ZeroSSL de respaldo), redirige HTTP a HTTPS sin configurarlo y toda la configuración entra en un archivo de unas 25 líneas.

| Opción | Certificados automáticos | Configuración para este caso | Veredicto |
| :---- | :---- | :---- | :---- |
| **Caddy** | Incluidos, sin nada extra | Un Caddyfile corto; reintentos hacia app durante los despliegues | **Recomendada** |
| Traefik | Incluidos | Etiquetas en cada contenedor; pensado para muchos servicios que aparecen y desaparecen | Más piezas de las necesarias para un solo sitio |
| Nginx | Requiere certbot y un cron de renovación | Archivo de configuración más largo; recarga manual tras renovar | Funciona, pero suma mantenimiento |

**Caddyfile de referencia**

{  
	email sistemas@integraldeleste.com.ar  
}

sistema.integraldeleste.com.ar {  
	encode zstd gzip

	header {  
		Strict-Transport-Security "max-age=31536000; includeSubDomains"  
		X-Content-Type-Options "nosniff"  
		X-Frame-Options "DENY"  
		Referrer-Policy "strict-origin-when-cross-origin"  
		Permissions-Policy "camera=(), microphone=(), geolocation=()"  
		\-Server  
	}

	request\_body {  
		max\_size 20MB  
	}

	reverse\_proxy app:3000 {  
		lb\_try\_duration 30s  
		lb\_try\_interval 500ms  
		health\_uri /salud  
		health\_interval 10s  
	}

	handle\_errors 502 503 {  
		respond "Sistema en mantenimiento. Volvé a intentar en unos minutos." 503  
	}

	log {  
		output stdout  
		format json  
	}  
}

* **Requisitos para el certificado:** un registro DNS A (y AAAA si hay IPv6) del subdominio apuntando a la IP del VPS, y los puertos 80 y 443 abiertos. Caddy valida por HTTP-01 o TLS-ALPN-01.  
* **Renovación:** Caddy renueva unos 30 días antes del vencimiento y guarda todo en el volumen caddy\_data. Si el volumen se pierde, vuelve a emitir en el primer arranque.  
* **lb\_try\_duration 30s:** mientras app se reinicia en un despliegue, Caddy retiene las peticiones hasta 30 segundos en lugar de devolver error 502 (sección 7).  
* **max\_size 20MB:** alcanza para las listas de precios en PDF o Excel que se suben en Configuración y para restaurar una copia.  
* **Content-Security-Policy:** la define app y no Caddy, porque depende de la SPA. Con las librerías empaquetadas puede ser default-src 'self'.  
* **SUPUESTO:** el sistema se publica en sistema.integraldeleste.com.ar. El correo para avisos de Let's Encrypt (sistemas@...) también es un ejemplo.

## **5\. PostgreSQL**

PostgreSQL 17 corre en el contenedor db con sus datos en el volumen pgdata. Se respalda con pg\_dump cada 6 horas a un almacenamiento externo cifrado, así que en el peor caso se pierden hasta 6 horas de trabajo (RPO ≤ 6 h).

### **Contenedor y configuración**

* **Inicialización:** POSTGRES\_INITDB\_ARGS="--data-checksums \--locale-provider=icu \--icu-locale=es-AR", para detectar corrupción de disco y ordenar apellidos con tildes y Ñ como en castellano.  
* **Ajustes para 2 GB de límite** (en postgresql.conf montado o como command:): shared\_buffers=512MB, effective\_cache\_size=1536MB, work\_mem=16MB, maintenance\_work\_mem=128MB, max\_connections=50.  
* **Conexiones:** app y worker usan un pool de hasta 10 conexiones cada uno. Con 5 usuarios a la vez no hace falta PgBouncer.  
* **Usuarios de base:** integral es dueño del esquema y lo usan las migraciones; la aplicación se conecta con integral\_app, sin permiso para borrar tablas. El respaldo usa integral\_backup, de solo lectura.  
* **Acceso de mantenimiento:** solo por túnel SSH (ssh \-L 5432:...) o con docker compose exec db psql. El puerto 5432 nunca se publica.

### **Respaldos automáticos**

| Qué | Cómo | Cuándo | Dónde queda |
| :---- | :---- | :---- | :---- |
| Volcado lógico de la base | pg\_dump \-Fc enviado por tubería a restic backup \--stdin | Cada 6 h (00, 06, 12 y 18 h) | Bucket S3 compatible en otro proveedor (Backblaze B2 o Cloudflare R2), cifrado por restic |
| Retención | restic forget \--prune \--keep-last 4 \--keep-daily 14 \--keep-weekly 8 \--keep-monthly 24 | Una vez por día | Mismo bucket |
| Verificación del repositorio | restic check \--read-data-subset=5% | Semanal | — |
| Prueba de restauración | Restaurar en una base temporal y contar afiliados, facturas y recibos | Mensual, con registro del resultado | — |
| Configuración del servidor | Copia de /opt/integral (sin secrets/) en el repositorio de despliegue; secretos en la bóveda del equipo | Con cada cambio | Repositorio Git y bóveda |
| Instantánea del VPS | Copia del panel de Hostinger, si el plan la incluye | Semanal | Hostinger (complementaria, no reemplaza a la externa) |

* **Protección contra borrado:** el bucket tiene versionado con bloqueo de objetos (*object lock*) de 30 días. Si alguien toma el VPS, no puede borrar las copias de ese período.  
* **Cifrado:** restic cifra antes de subir. Sin la contraseña de restic las copias no sirven, así que esa contraseña se guarda en la bóveda del equipo y en un segundo lugar físico.  
* **Aviso:** al terminar bien, el contenedor llama a HEARTBEAT\_BACKUP\_URL; si falla, llama a la misma URL con /fail. Si en 7 horas no llegó ningún aviso, el monitoreo alerta.  
* **Pantalla Copias de seguridad:** la maqueta permite descargar y restaurar un JSON desde Configuración. En el sistema instalado esa pantalla muestra el estado de la última copia automática (fecha, resultado) y alerta en rojo si tiene más de 7 días, como hoy. Ver PREGUNTA ABIERTA P-05.

Núcleo del script de respaldo:

set \-euo pipefail  
export PGPASSWORD="\$(cat "\$DB\_PASSWORD\_FILE")"  
if pg\_dump \-h db \-U integral\_backup \-Fc \-Z 6 integral \\  
   \| restic backup \--stdin \--stdin-filename integral.dump \--tag pg; then  
  \[ "\$(date \+%H)" \= "00" \] && restic forget \--prune \$BACKUP\_RETENTION  
  curl \-fsS \-m 10 "\$HEARTBEAT\_BACKUP\_URL"  
else  
  curl \-fsS \-m 10 "\$HEARTBEAT\_BACKUP\_URL/fail"; exit 1  
fi

### **Restauración**

**A. Volver la base a una copia (el VPS sigue funcionando)**

1. Elegir la copia: docker compose run \--rm backup restic snapshots \--tag pg.  
2. Avisar a los usuarios y detener la aplicación: docker compose stop app worker. Caddy responde con la página de mantenimiento.  
3. Crear una base vacía: docker compose exec db createdb \-U integral integral\_restaurada.  
4. Restaurar desde restic directo a esa base: docker compose run \--rm backup sh \-c 'restic dump \<id\> integral.dump' \| docker compose exec \-T db pg\_restore \-U integral \-d integral\_restaurada \--no-owner  
5. Comprobar: cantidad de afiliados, grupos, última factura y último recibo, contra lo esperado.  
6. Intercambiar las bases con psql: ALTER DATABASE integral RENAME TO integral\_anterior; ALTER DATABASE integral\_restaurada RENAME TO integral;.  
7. Levantar: docker compose start app worker y verificar el ingreso.  
8. **Conciliar con ARCA:** las facturas que obtuvieron CAE después de la copia están en ARCA pero no en la base. El worker consulta el último comprobante autorizado de cada punto de venta (FECompUltimoAutorizado) y trae los faltantes con FECompConsultar antes de volver a facturar.  
9. Borrar integral\_anterior después de una semana sin problemas.

**B. El VPS se perdió (restauración completa en un servidor nuevo)**

1. Contratar otro VPS, aplicar el endurecimiento de la sección 6 e instalar Docker.  
2. Recuperar /opt/integral del repositorio de despliegue y secrets/ de la bóveda.  
3. docker compose up \-d db y restaurar como en A.3 a A.5 sobre la base integral.  
4. docker compose up \-d para el resto de los servicios.  
5. Cambiar el registro DNS a la nueva IP. Caddy emite el certificado en el primer pedido.  
6. Conciliar con ARCA como en A.8.

**Objetivo de tiempo de recuperación (SUPUESTO):** 2 horas para el caso B, con este procedimiento ensayado. Confirmar si administración acepta ese tiempo (P-04).

## **6\. Seguridad**

Desde Internet solo se ven los puertos 80 y 443 (Caddy) y el SSH con clave pública; todo lo demás queda en redes internas de Docker. En la maqueta los permisos se controlan en el navegador; en el sistema instalado la API valida cada pedido.

### **Gestión de secretos**

* Cada secreto es un archivo en /opt/integral/secrets/ (dueño root, carpeta 700, archivos 600) que Compose monta en /run/secrets/ solo en los contenedores que lo necesitan (sección 3). Ningún secreto va en el docker-compose.yml, en la imagen ni en Git.  
* El original de cada secreto vive en la bóveda del equipo (por ejemplo Bitwarden o 1Password), que es desde donde se reconstruye el servidor.  
* **Rotación:** contraseñas de base, SMTP y claves S3 una vez por año y cada vez que alguien con acceso deja el equipo. El certificado de ARCA tiene vencimiento: el worker alerta 30 días antes.  
* La clave de acceso S3 del VPS puede escribir y leer, pero no saltar el bloqueo de objetos (sección 5).

### **Seguridad de la aplicación**

* **Claves de usuario:** guardadas con Argon2id. La maqueta crea las claves iniciales iguales al nombre de usuario; en el sistema instalado se piden cambiar en el primer ingreso (SUPUESTO, P-06).  
* **Sesión:** del lado del servidor, en una cookie HttpOnly, Secure, SameSite=Strict. Se cierra a los 15 minutos sin actividad, como en la maqueta.  
* **Intentos de ingreso:** límite de 5 intentos fallidos por usuario en 15 minutos y por IP, con espera creciente.  
* **Permisos:** la API comprueba el rol (administrador, usuario, unidad o comercio) y las secciones habilitadas de cada usuario en cada pedido, no solo en la pantalla.  
* **Autorización de un administrador:** las acciones que en la maqueta piden usuario y clave de un administrador (Configuración → Qué requiere administrador) se resuelven en la API con una revalidación que vale para esa sola acción y queda en el historial.  
* **Historial de cambios:** tabla de solo agregado; la aplicación no tiene permiso para modificar ni borrar sus filas.  
* **Datos personales:** el padrón tiene DNI, CUIT, teléfono, mail y fecha de nacimiento. Aplica la Ley 25.326 de Protección de Datos Personales: acceso solo para quien lo necesita (como ya define la maqueta) y copias cifradas.

### **Firewall y puertos expuestos**

Dos capas: el firewall del panel de Hostinger y UFW dentro del VPS, ambos con "denegar todo lo entrante" por defecto.

| Puerto | Protocolo | Abierto a | Servicio |
| :---- | :---- | :---- | :---- |
| 22 (o el que se elija) | TCP | Todo Internet o solo IPs fijas de la oficina (P-07) | SSH |
| 80 | TCP | Todo Internet | Caddy: validación del certificado y redirección a HTTPS |
| 443 | TCP y UDP | Todo Internet | Caddy: HTTPS y HTTP/3 |
| 5432, 3000 y cualquier otro | — | Nadie | Solo en redes internas de Docker |

**Cuidado con Docker y UFW:** Docker publica puertos salteándose las reglas de UFW. Por eso la regla es que solo caddy tenga ports: en el compose. Después de cada cambio se comprueba desde afuera con nmap \-Pn \<ip\> que solo respondan 22, 80 y 443\.

### **Acceso SSH**

* Solo con clave pública (ed25519), una por persona: PasswordAuthentication no, PermitRootLogin no.  
* Un usuario deploy para administrar. Pertenecer al grupo docker equivale a ser root, así que se limita a quienes operan el servidor.  
* fail2ban sobre SSH; acceso al panel de Hostinger con doble factor.  
* Al irse una persona del equipo, se borra su clave de authorized\_keys y se rotan los secretos que conocía.

### **Actualización de imágenes y del sistema operativo**

* **Sistema operativo:** unattended-upgrades aplica los parches de seguridad todos los días. Los reinicios que pidan se hacen a mano en la ventana mensual.  
* **Imágenes:** versiones fijas en el compose (caddy:2.8, postgres:17, nunca latest). Una vez por mes: docker compose pull, docker compose up \-d y control rápido de funcionamiento.  
* **Dependencias propias:** Renovate o Dependabot abre los pedidos de actualización en el repositorio, y el CI escanea la imagen con Trivy antes de publicarla.  
* **Cambio de versión mayor de PostgreSQL:** tarea planificada aparte, con pg\_dump y restauración en la versión nueva, en un horario sin uso.

## **7\. Despliegue de nuevas versiones**

Se recomienda CI/CD con GitHub Actions y aprobación manual del paso a producción. Cada versión deja a los usuarios sin servicio menos de 10 segundos, y Caddy retiene las peticiones durante ese reinicio, así que en general nadie ve un error.

![][image2]

*Figura 2\. Despliegue de una nueva versión con CI/CD.*

**Qué hace deploy.sh en el VPS**

1. Hace una copia de la base en el momento: docker compose run \--rm backup /backup.sh.  
2. Descarga la imagen nueva: docker compose pull app worker con APP\_TAG=X.Y.Z.  
3. Corre las migraciones de base: docker compose run \--rm app node dist/migrate.js.  
4. Reemplaza los contenedores: docker compose up \-d \--no-deps app worker.  
5. Espera el healthcheck de app y prueba https\://\<dominio\>/salud desde afuera.  
6. Si algo falla, vuelve a poner el APP\_TAG anterior y repite el paso 4\. Guarda en /opt/integral/deploys.log quién desplegó qué versión y cuándo.

**Reglas para minimizar cortes**

* **Migraciones compatibles hacia atrás** (expandir y después contraer): una versión agrega columnas o tablas sin romper la anterior; lo que se borra o renombra se borra en una versión posterior. Así la vuelta atrás del paso 6 no choca con la base.  
* **Apagado ordenado del worker:** al recibir SIGTERM termina la tarea en curso (stop\_grace\_period: 60s), así no se corta un pedido de CAE ni un envío de mails a la mitad.  
* **Horario:** las versiones con migraciones grandes, fuera del horario de atención. Las chicas, en cualquier momento.  
* **Número de versión:** la maqueta muestra la lista "Versiones del sistema" en Configuración. El build incluye el número y el texto de cambios, y la API los expone para esa pantalla.  
* **Ambiente de pruebas (recomendado, P-08):** otro proyecto de Compose en el mismo VPS (staging.integraldeleste.com.ar), con su propia base, límites de memoria bajos y ARCA en homologación. Cada versión pasa por ahí antes de producción.

**Alternativa manual:** el mismo deploy.sh X.Y.Z corrido por SSH. Sirve para empezar, pero depende de que la persona siga los pasos y deja menos registro.

## **8\. Observabilidad**

Para un solo servidor alcanza con logs locales rotados, un monitoreo externo gratuito que mira el sitio y los avisos de las tareas programadas, y alertas por mail. No se instala Prometheus ni Grafana en el VPS.

### **Logs**

* Todos los contenedores escriben a la salida estándar; Docker los guarda con rotación json-file de 10 MB × 5 archivos por contenedor (unos 250 MB en total como máximo).  
* app y worker escriben en JSON, con identificador de pedido, usuario y acción. Nunca escriben claves, tokens ni el contenido de los datos personales.  
* Consulta: docker compose logs \-f \--since 1h app. Si hiciera falta guardarlos más tiempo o buscarlos con comodidad, se pueden enviar a un servicio externo (por ejemplo Better Stack o Grafana Cloud, en su plan gratuito).  
* El **historial de cambios** de la aplicación (quién dio de baja, quién cambió un precio) es otra cosa: vive en la base y se ve en Configuración, como en la maqueta.

### **Monitoreo y alertas**

| Qué se vigila | Cómo | Alerta cuando | Canal |
| :---- | :---- | :---- | :---- |
| El sitio responde | Monitor externo (UptimeRobot o Better Stack) sobre GET /salud, que también prueba la conexión a la base | 2 fallas seguidas, con chequeo cada 5 min | Mail al equipo técnico (P-11) |
| Certificado TLS | El mismo monitor externo | Faltan menos de 14 días para que venza | Mail |
| Copias de seguridad | Aviso de backup a Healthchecks.io | No llegó aviso en 7 h, o llegó /fail | Mail |
| Worker vivo | Aviso del worker cada 5 min | No llegó aviso en 15 min | Mail |
| Disco, memoria | Script en cron cada 15 min que avisa a Healthchecks.io con /fail si se pasa del umbral | Disco \> 80 % o memoria disponible \< 10 % | Mail |
| Tareas fallidas | Tabla de la cola en la base | Un pedido de CAE rechazado o un mail que rebota | Aviso dentro del sistema a los administradores, y mail |
| Certificado de ARCA | El worker revisa el vencimiento cada día | Faltan menos de 30 días | Mail |

* Las métricas de CPU, memoria y disco del panel de Hostinger sirven para ver tendencias; no hace falta otra herramienta.  
* El monitor de disponibilidad está fuera del VPS a propósito: si el servidor se cae entero, el aviso tiene que salir de otro lado.

## **9\. Riesgos y limitaciones de un único VPS**

El riesgo principal es que el VPS es un punto único de falla: si se cae, el sistema no está disponible hasta que vuelva o se restaure en otro servidor (objetivo: 2 horas). Se acepta porque el costo de alta disponibilidad no se justifica para 20 usuarios, y se mitiga con copias externas y un procedimiento de recuperación ensayado.

| Riesgo | Efecto | Mitigación |
| :---- | :---- | :---- |
| Caída del VPS o del centro de datos de Hostinger | Nadie puede trabajar: no se cobra en mostrador ni se verifican afiliados | Monitoreo externo; restauración completa en otro VPS (sección 5.B); objetivo 2 h |
| Pérdida o corrupción de datos | Se pierden facturas, cobros o usos | Copia cada 6 h fuera del VPS con bloqueo de 30 días; *checksums* de PostgreSQL; prueba de restauración mensual |
| Facturas con CAE posteriores a la última copia | Al restaurar faltan comprobantes que ARCA sí tiene | Conciliación con ARCA antes de volver a facturar (sección 5, paso A.8) |
| Servidor comprometido | Robo de datos personales o borrado | Solo 3 puertos abiertos, SSH por clave, secretos fuera de Git, copias cifradas que no se pueden borrar desde el VPS |
| Error en una versión o en una migración | Sistema caído o datos mal escritos | Copia previa a cada despliegue, migraciones compatibles hacia atrás, vuelta automática a la versión anterior, ambiente de pruebas |
| Disco lleno | La base deja de escribir | Rotación de logs, copias fuera del VPS, alerta al 80 % |
| ARCA no responde | No se obtiene el CAE | La factura queda "pendiente de CAE" en la cola y el worker reintenta (SUPUESTO: la cobranza no espera al CAE, P-03) |
| Mails marcados como spam | Los afiliados no reciben facturas ni recibos | Proveedor SMTP transaccional, SPF, DKIM y DMARC en el dominio; seguimiento de rebotes |
| Falta de Internet en la oficina o en una unidad | La maqueta funcionaba sin conexión; el sistema instalado no | Datos móviles del celular como respaldo; el sistema funciona desde el navegador del celular |
| Mantenimiento del servidor | Cortes de minutos al reiniciar o actualizar | Ventana mensual fuera del horario de atención, avisada a los usuarios |
| Una sola persona conoce el servidor | Nadie sabe restaurar si esa persona no está | Este documento, deploy.sh y los procedimientos versionados en el repositorio; secretos en una bóveda compartida |

**Escalado**

* Con 5 usuarios a la vez la carga es mínima; el cuello de botella posible son las tareas masivas (facturar el mes, mandar todas las facturas por mail), que corren en el worker sin frenar la API.  
* **Vertical:** si el uso crece, se pasa a un plan mayor de Hostinger (KVM4) desde el panel; requiere un reinicio. Indicador para hacerlo: CPU por encima de 70 % o memoria disponible por debajo de 15 % sostenidas durante una semana.  
* **Horizontal:** no está previsto. Si algún día hiciera falta, el primer paso es mover PostgreSQL a un servicio administrado; app no guarda estado propio, así que se puede duplicar detrás de Caddy.

## **10\. Decisiones de arquitectura (ADR)**

Las decisiones 01 y 03 vienen dadas por el contexto técnico; las demás son propuestas de este documento y quedan para aprobar. La ADR-07 (lenguaje) es la que más conviene confirmar antes de empezar a programar.

| ADR | Decisión | Alternativas consideradas | Motivo |
| :---- | :---- | :---- | :---- |
| ADR-01 | Un único VPS Hostinger KVM2 con Docker Compose | Kubernetes; PaaS (Render, Railway); dos VPS con réplica | Dado por el contexto. Para 20 usuarios, Compose da reproducibilidad con mínima operación; Kubernetes y la réplica suman costo y complejidad sin necesidad. |
| ADR-02 | Caddy como reverse proxy con TLS automático | Nginx \+ certbot; Traefik | Certificados y redirección HTTPS sin configuración extra, archivo corto, reintentos hacia app durante el despliegue. Traefik rinde más con muchos servicios dinámicos, que acá no hay. |
| ADR-03 | PostgreSQL 17 en contenedor, en el mismo VPS | PostgreSQL administrado (por ejemplo Neon, Supabase, RDS) | Dado por el contexto. Sin costo extra y con latencia mínima; el riesgo de pérdida se cubre con las copias externas. |
| ADR-04 | pg\_dump cada 6 h con restic a un bucket S3 externo, con bloqueo de objetos | Archivado continuo de WAL (WAL-G, pgBackRest); solo instantáneas de Hostinger | La base es chica (padrón de decenas o cientos de afiliados) y el volcado tarda segundos. WAL da recuperación al minuto pero agrega operación; queda como mejora si P-04 pide RPO menor. Las instantáneas solas quedan en el mismo proveedor. |
| ADR-05 | Monolito modular: una imagen con API \+ SPA; el worker usa la misma imagen | Microservicios; frontend en otro contenedor | Un solo artefacto para construir, versionar y volver atrás. La maqueta ya es una sola aplicación con módulos claros (afiliados, caja, beneficios, configuración). |
| ADR-06 | Cola de tareas guardada en PostgreSQL (por ejemplo pg-boss o Graphile Worker) | Redis \+ BullMQ; RabbitMQ | El volumen es de cientos de tareas por mes. Usar la misma base evita otro servicio y otra copia de seguridad, y las tareas entran en la misma transacción que el dato que las origina. |
| ADR-07 | TypeScript en backend (Node.js) y frontend (SUPUESTO) | Python \+ Django; PHP \+ Laravel | La maqueta está en JavaScript: las reglas de cuotas, promociones y beneficios se pueden portar y compartir entre pantalla y API, y las librerías de PDF y Excel ya usadas existen para Node. Confirmar según el equipo que desarrolle (P-01). |
| ADR-08 | Secretos como archivos montados por Docker Compose, originales en una bóveda | Variables en .env; HashiCorp Vault | Los archivos no aparecen en docker inspect ni en las variables del proceso. Vault es desproporcionado para un servidor. |
| ADR-09 | CI/CD con GitHub Actions, imágenes en GHCR, aprobación manual del paso a producción | Despliegue manual por SSH; Watchtower (actualiza solo al publicar una imagen) | Deja registro de quién desplegó qué, corre tests y escaneo antes, y permite volver atrás por versión. Watchtower despliega sin migraciones ni control. |
| ADR-10 | PDF generados en el navegador para descargar y compartir; en el servidor solo para los mails automáticos | Todo en el servidor; todo en el navegador | Mantiene el comportamiento de la maqueta y la carga fuera del VPS; los mails automáticos con adjunto no tienen navegador. Conviene una sola plantilla compartida para que ambos PDF salgan iguales. |
| ADR-11 | Monitoreo externo SaaS gratuito \+ avisos de tareas, alertas por mail | Prometheus \+ Grafana \+ Alertmanager en el VPS; Netdata | Detecta la caída total del VPS, cosa que un monitor dentro del mismo VPS no puede. Proporcional a un solo servidor. |

## **11\. Supuestos y preguntas abiertas**

Las 13 preguntas de abajo hay que responderlas antes de instalar; las de ARCA (P-03), mail (P-02) y lenguaje (P-01) frenan el desarrollo si quedan sin respuesta.

### **Supuestos**

| ID | Supuesto | Sección |
| :---- | :---- | :---- |
| S-01 | El sistema se publica en sistema.integraldeleste.com.ar y el equipo controla el DNS del dominio. | 3, 4 |
| S-02 | El KVM2 tiene 2 vCPU y 8 GB de RAM; los límites de memoria se calcularon con esos valores. | 3 |
| S-03 | Los PDF se siguen generando en el navegador; el servidor los genera solo para los mails automáticos. | 2, ADR-10 |
| S-04 | El backend se escribe en TypeScript sobre Node.js. | ADR-07 |
| S-05 | Un tiempo de recuperación de 2 horas ante la pérdida del VPS es aceptable. | 5, 9 |
| S-06 | Las claves iniciales (hoy iguales al nombre de usuario) se cambian obligatoriamente en el primer ingreso. | 6 |
| S-07 | Si ARCA no responde, la factura queda pendiente de CAE y se reintenta, sin frenar la cobranza. | 9 |
| S-08 | WhatsApp sigue funcionando con enlaces wa.me y "Compartir" desde el navegador; no hay integración con WhatsApp Business. | 2 |

### **Preguntas abiertas**

| ID | Pregunta | Afecta a |
| :---- | :---- | :---- |
| P-01 | ¿Quién desarrolla y opera el sistema, en qué lenguaje prefiere trabajar y dónde va a estar el repositorio (GitHub u otro)? | ADR-07, ADR-09, sección 7 |
| P-02 | ¿Qué servicio de mail usa hoy integraldeleste.com.ar y qué proveedor SMTP transaccional se contrata para sistema@integraldeleste.com.ar? | 2, 9 |
| P-03 | ¿Grupo del Este Salud S.A.S. ya tiene certificado digital y punto de venta habilitado para factura electrónica por web service? ¿Se puede cobrar una cuota cuya factura todavía no tiene CAE? | 2, 5, 9 |
| P-04 | ¿Cuánta información se puede perder como máximo (hoy 6 h) y cuánto tiempo puede estar caído el sistema (hoy 2 h)? | 5, ADR-04 |
| P-05 | ¿Se mantiene en la pantalla Copias de seguridad la descarga manual de toda la base y la restauración con clave de administrador? Implica tener datos personales en una PC. | 5 |
| P-06 | ¿Cómo se entregan las claves iniciales a cada usuario, unidad y comercio? | 6 |
| P-07 | ¿La oficina tiene IP fija para limitar el acceso SSH, o se deja abierto con clave pública y fail2ban? | 6 |
| P-08 | ¿Se aprueba un ambiente de pruebas en el mismo VPS, con ARCA en homologación? | 7 |
| P-09 | ¿Cuánto tiempo hay que conservar facturas, recibos y datos de afiliados dados de baja? Confirmar con el contador; la retención de copias es hoy de 24 meses. | 5 |
| P-10 | ¿Con qué datos arranca el sistema: el padrón del Excel (24 afiliados al 31/10/2026, como la maqueta) o lo que se haya cargado en la demo? | 5, 7 |
| P-11 | ¿Quién recibe las alertas técnicas y por qué canal (mail, Telegram, WhatsApp)? | 8 |
| P-12 | El débito automático por Mercado Pago hoy se registra a mano en Caja. ¿Se mantiene así o se prevé una integración? | 2 |
| P-13 | ¿En qué centro de datos de Hostinger va el VPS? Si queda fuera de Argentina, revisar con un asesor la transferencia internacional de datos personales (Ley 25.326). | 6, 9 |

[image1]: <data:image/png;base64,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>

[image2]: <data:image/png;base64,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>