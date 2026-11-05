# Integral del Este

Sistema de gestión del plan de beneficios de salud **Integral del Este** (Grupo del Este Salud S.A.S.). Cubre:

- padrón de afiliados, altas, bajas y retención;
- cotizador y promociones;
- autorizaciones y usos en unidades y comercios adheridos;
- facturación electrónica con ARCA, cobranza y cuenta corriente;
- egresos, contabilidad, estadísticas e informes;
- configuración.

Implementa los 52 casos de uso de `Integral_del_Este_-_Casos_de_uso.docx.md` sobre la maqueta `Integral_del_Este_-_demo_v82.html`, siguiendo `Integral_del_Este_-_Arquitectura.docx.md`, con PHP 8.3 y PostgreSQL 17.

## Documentación

| Documento | Para qué |
|---|---|
| [docs/INSTALACION.md](docs/INSTALACION.md) | Instalación en un VPS de Hostinger con Ubuntu 24.04 y Docker Compose; ambiente de pruebas; desarrollo local |
| [docs/EASYPANEL.md](docs/EASYPANEL.md) | Despliegue automático desde GitHub en un VPS con EasyPanel: un servicio Postgres y la aplicación, unidos por `DATABASE_URL` |
| [docs/OPERACION.md](docs/OPERACION.md) | Tareas del día a día: nuevas versiones, copias de seguridad, restauración, monitoreo, rotación de secretos |
| [docs/ARCA.md](docs/ARCA.md) | Certificado, homologación y producción de la factura electrónica (WSAA / WSFEv1) |
| [docs/PLAN_DE_PRUEBAS.md](docs/PLAN_DE_PRUEBAS.md) | Lotes de prueba, pruebas automáticas y matriz de aceptación por caso de uso |
| [docs/DECISIONES.md](docs/DECISIONES.md) | Cómo se resolvió cada pregunta abierta (P-xx) y cada supuesto (S-xx) de los documentos |

## Estructura

```
public/          index.php (único punto de entrada) y assets/ (CSS de la maqueta, JavaScript propio)
src/Core/        núcleo: router, pedidos, base de datos, sesión, vistas, configuración
src/Domain/      reglas de negocio (padrón, beneficios RT-06, caja, retención, gestión…)
src/Service/     PDF, informes Excel/PDF, mail, worker, ARCA (simulado y web service)
src/Http/        controladores por sección; rutas en src/rutas*.php
views/           plantillas de las pantallas, modales y PDF
bin/             migrar.php, worker.php, crear-admin.php
database/        scripts de creación (roles, esquema, vistas, catálogos, padrón inicial)
database/lotes/  lotes de prueba L00 a L08
Dockerfile       imagen "todo en uno" (web + worker + migraciones + copias) para EasyPanel
deploy/          despliegue con Docker Compose: Dockerfile, docker-compose.yml, Caddyfile, copias, deploy.sh
deploy/integral/ arranque y procesos de la imagen "todo en uno"
tests/           PHPUnit (Unit, Integracion, Http) y prueba de humo en el navegador (e2e)
```

## Base de datos

Los scripts de `database/` se aplican en orden con `php bin/migrar.php`, que los registra en `schema_migraciones`. También se pueden correr a mano con `psql -f`.

| Script | Contenido |
|---|---|
| `001_roles.sql` | Roles `integral_app` (la aplicación, sin DDL) e `integral_backup` (solo lectura) |
| `002_esquema.sql` | Tablas, secuencias, restricciones e índices |
| `003_vistas.sql` | Plan de cuentas, estado de cuenta (RT-05), asientos contables, usos con ahorro |
| `010_catalogos.sql` | Planes, categorías, precios, prestaciones, reglas, unidades, rubros, mails, configuración |
| `011_padron_inicial.sql` | Padrón inicial (24 afiliados) y usuarios |

## Inicio rápido (desarrollo local, sin Docker)

Requisitos: PHP 8.3 (`pdo_pgsql`, `intl`, `mbstring`, `gd`, `zip`, `curl`), Composer y PostgreSQL 16 o 17.

```bash
sudo -u postgres psql -c "CREATE ROLE integral LOGIN PASSWORD 'integral' CREATEDB CREATEROLE"
```

```bash
sudo -u postgres createdb -O integral integral
```

```bash
cp .env.example .env
```

```bash
composer install
```

```bash
php bin/migrar.php --lotes
```

```bash
php -S 127.0.0.1:8080 -t public
```

Abrir http://127.0.0.1:8080 e ingresar como **Santiago** con clave **Santiago** (con los lotes cargados, la clave de cada usuario es su nombre). Los mails quedan en `storage/mails/` y ARCA funciona en modo simulado. Para procesar la cola de CAE y mails:

```bash
php bin/worker.php
```

## Pruebas

```bash
vendor/bin/phpunit
```

Crea la base `integral_test` desde cero con los scripts y los lotes, y corre 111 pruebas (reglas, casos de uso, permisos, sesión, ARCA con respuestas de ejemplo). Detalle en [docs/PLAN_DE_PRUEBAS.md](docs/PLAN_DE_PRUEBAS.md).

## Producción

**Con EasyPanel** (lo más simple): un servicio Postgres y un servicio App desde este repositorio, que usa el `Dockerfile` de la raíz. La única variable obligatoria es `DATABASE_URL`. Las migraciones, el worker y las copias arrancan solos. Paso a paso en [docs/EASYPANEL.md](docs/EASYPANEL.md).

Imagen "todo en uno" con Docker, sin EasyPanel:

```bash
docker build -t integral .
```

```bash
docker run -d -p 8080:80 -e DATABASE_URL=postgres://usuario:clave@host:5432/integral integral
```

**Sin panel:**

Docker Compose en un VPS (Caddy con HTTPS automático, la aplicación, el worker, PostgreSQL 17 y las copias cifradas con restic). Paso a paso en [docs/INSTALACION.md](docs/INSTALACION.md).
