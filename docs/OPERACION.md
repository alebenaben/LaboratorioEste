# Integral del Este — Operación del servidor

Tareas de quien administra el VPS. Los comandos se corren en `/opt/integral/deploy` como el usuario `deploy`.

## Comandos de todos los días

| Para qué | Comando |
|---|---|
| Ver el estado de los servicios | `docker compose ps` |
| Ver los logs de la aplicación | `docker compose logs -f --since 1h app` |
| Ver los logs del worker (CAE, mails) | `docker compose logs -f worker` |
| Reiniciar la aplicación | `docker compose restart app worker` |
| Entrar a la base | `docker compose exec db psql -U integral -d integral` |
| Procesar la cola a mano | `docker compose exec worker php bin/worker.php --una-vez` |
| Estado de las migraciones | `docker compose run --rm migrar php bin/migrar.php --estado` |
| Poner clave a un administrador | `docker compose run --rm migrar php bin/crear-admin.php Santiago 'Clave'` |

## Nueva versión

1. En el repositorio, etiquetar la versión: `git tag v1.0.1 && git push --tags`.
2. En el servidor:

   ```bash
   ./deploy.sh v1.0.1
   ```

`deploy.sh` hace estos pasos:

1. Una copia de la base.
2. Baja el código de esa etiqueta y construye la imagen.
3. Corre las migraciones.
4. Reemplaza `app` y `worker`.
5. Comprueba `/salud`.

Si algo falla, vuelve solo a la versión anterior. Cada despliegue queda anotado en `deploys.log`. Caddy retiene los pedidos hasta 30 s mientras la aplicación se reinicia.

**Migraciones:** cada cambio de base es un archivo nuevo `database/0NN_descripcion.sql`, compatible hacia atrás:

- primero se agrega lo nuevo;
- lo viejo se borra en una versión posterior.

Nunca se edita un script ya aplicado.

## Actualizaciones de imágenes y del sistema operativo

- Ubuntu aplica los parches de seguridad solo (`unattended-upgrades`). Los reinicios que pidan (`/var/run/reboot-required`) se hacen a mano en la ventana mensual, fuera del horario de atención.
- Una vez por mes:

  ```bash
  docker compose pull caddy db
  docker compose build --pull
  docker compose up -d
  ```

  Después, un control rápido: ingresar y ver el padrón.
- **Cambio de versión mayor de PostgreSQL** (17 → 18): tarea aparte. Se hace un `pg_dump` y se restaura en la versión nueva, en un horario sin uso.

## Copias de seguridad

- **Automáticas:** cada 6 horas (00, 06, 12 y 18 h), `pg_dump` cifrado con restic al bucket externo.
  - **Retención:** las últimas 4, una por día durante 14 días, una por semana durante 8 semanas y una por mes durante 24 meses.
  - **Verificación:** los domingos a las 00 h se verifica el 5 % de los datos.
- **Copia en el momento:** `docker compose run --rm backup /backup.sh`.
- **Estado:** Configuración → Copias de seguridad (en rojo si la última correcta tiene más de 7 días) y el aviso en Healthchecks.io.
- **Listar las copias:** `docker compose run --rm backup restic snapshots --tag pg`.
- **Prueba de restauración mensual (recomendada):** restaurar en una base temporal y contar afiliados, facturas y recibos (pasos A.3 a A.5 de abajo). Después se borra la base temporal y se anota la fecha y el resultado.

### A. Volver la base a una copia (el VPS sigue funcionando)

1. Elegir la copia:

   ```bash
   docker compose run --rm backup restic snapshots --tag pg
   ```

2. Avisar a los usuarios y detener la aplicación. Caddy muestra "Sistema en mantenimiento".

   ```bash
   docker compose stop app worker
   ```

3. Crear una base vacía:

   ```bash
   docker compose exec db createdb -U integral integral_restaurada
   ```

4. Restaurar (reemplazá `<id>` por el de la copia elegida):

   ```bash
   docker compose run --rm -T backup sh -c 'export AWS_SECRET_ACCESS_KEY=$(cat $AWS_SECRET_ACCESS_KEY_FILE); restic dump <id> integral.dump' | docker compose exec -T db pg_restore -U integral -d integral_restaurada --no-owner --role=integral
   ```

5. Comprobar:

   ```bash
   docker compose exec db psql -U integral -d integral_restaurada -c "select (select count(*) from afiliados) afiliados, (select count(*) from grupos) grupos, (select max(numero) from facturas) ult_factura, (select max(numero) from recibos) ult_recibo"
   ```

6. Intercambiar las bases:

   ```bash
   docker compose exec db psql -U integral -d postgres -c "ALTER DATABASE integral RENAME TO integral_anterior" -c "ALTER DATABASE integral_restaurada RENAME TO integral"
   ```

7. Volver a dar permisos a la aplicación y levantarla:

   ```bash
   docker compose run --rm migrar
   docker compose start app worker
   ```

8. **Conciliar con ARCA:** las facturas que obtuvieron CAE después de la copia están en ARCA, pero no en la base. Antes de volver a facturar:
   - En Configuración → Puntos de venta, el **último N°** tiene que coincidir con el último autorizado en ARCA.
   - El worker, al pedir un CAE, compara con `FECompUltimoAutorizado`. Si la numeración local quedó atrás, avisa en rojo y no factura hasta corregirlo.
   - Las facturas ya autorizadas que se vuelvan a emitir con el mismo número se recuperan con `FECompConsultar`.
9. Pasada una semana sin problemas, borrar la base anterior:

   ```bash
   docker compose exec db dropdb -U integral integral_anterior
   ```

### B. El VPS se perdió (servidor nuevo)

1. Crear otro VPS y seguir [INSTALACION.md](INSTALACION.md) hasta el paso 6.
2. Recuperar `deploy/.env`, `deploy/.env.backup` y `deploy/secrets/` de la bóveda del equipo.
3. Levantar la base y restaurar la copia sobre `integral` (pasos A.3 a A.5, con `integral` en lugar de `integral_restaurada`):

   ```bash
   docker compose build
   docker compose up -d db
   ```

4. Correr `docker compose run --rm migrar` y después `docker compose up -d`.
5. Cambiar el registro DNS a la IP nueva. Caddy emite el certificado en el primer pedido.
6. Conciliar con ARCA como en A.8.

Objetivo de tiempo de recuperación: 2 horas, con el procedimiento ensayado (P-04).

## Monitoreo de disco y memoria

Script para el cron del usuario `deploy` (`crontab -e`):

```
*/15 * * * * /opt/integral/deploy/chequeo.sh
```

`/opt/integral/deploy/chequeo.sh` (reemplazá `<id>` por el del check de Healthchecks.io):

```bash
#!/usr/bin/env bash
URL="https://hc-ping.com/<id>"
DISCO=$(df --output=pcent / | tail -1 | tr -dc 0-9)
MEM=$(awk '/MemAvailable/{a=$2}/MemTotal/{t=$2}END{print int(a*100/t)}' /proc/meminfo)
if [ "$DISCO" -gt 80 ] || [ "$MEM" -lt 10 ]; then curl -fsS -m 10 "$URL/fail" >/dev/null; else curl -fsS -m 10 "$URL" >/dev/null; fi
```

## Avisos dentro del sistema

Los administradores ven franjas en todas las pantallas cuando:

- ARCA rechaza una factura, o la numeración local no coincide con la de ARCA;
- un mail rebota (dirección inválida): la factura o el recibo quedan marcados "rebotó";
- el certificado de ARCA vence en menos de 30 días;
- una tarea falla después de varios reintentos.

"Entendido" marca el aviso como leído.

## Logs

- Docker guarda hasta 5 archivos de 10 MB por contenedor.
- La aplicación no escribe claves ni datos personales en los logs.
- El **historial de cambios** (quién dio de baja, quién cambió un precio, ingresos y salidas) está en la base, en Configuración → Historial de cambios. La aplicación no lo puede editar ni borrar.

## Seguridad: rotación de secretos

Una vez por año, y cada vez que alguien con acceso deja el equipo:

1. Generar la clave nueva:
   - **base de datos:** editar `secrets/db_app_password` o `secrets/db_backup_password` y correr `docker compose run --rm migrar` (vuelve a poner las claves de los roles);
   - **SMTP y S3:** cambiarlas en el proveedor y en `secrets/`.
2. `docker compose up -d` para que los contenedores tomen los secretos nuevos.
3. Actualizar la bóveda.
4. Borrar la clave SSH de la persona de `~/.ssh/authorized_keys`.

La clave de **restic** no se rota: si se pierde, las copias no se pueden leer.
