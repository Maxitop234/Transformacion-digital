# infra/docker · Contenedores

Dockerfiles y archivos Compose del sistema.

| Archivo | Uso | Tarea |
| --- | --- | --- |
| `web/Dockerfile` | Imagen PHP-FPM 8.4 con `pdo_pgsql`, `intl`, `zip` y `opcache` para Laravel (web y worker) | [T-002](../../tasks/T-002-entorno-local-docker/README.md) |
| `nginx/` | Nginx: publica Laravel y deriva `/bi` a Grafana | T-002 |
| `db/01-inicializar-dev.sh` | Inicialización local de la base: usuarios con [`db/roles.sql`](../../db/roles.sql), PostGIS, base de tests `cofrial_test` y base interna de Grafana | T-002, T-003, T-005 |
| `compose.dev.yml` | Entorno local completo: base, web, nginx, worker y Grafana (el simulador se agrega en T-019) | T-002, T-019 |
| `compose.prod.yml` (por crear) | EC2 web: Nginx, aplicación, worker, planificador y Grafana | [T-024](../../tasks/T-024-despliegue-web-y-balanceador/README.md) |
| `compose.iot.yml` (por crear) | Nodo IoT: cinco contenedores del simulador | [T-025](../../tasks/T-025-despliegue-nodo-iot/README.md) |

Todos los servicios usan reinicio automático (`restart: unless-stopped`), porque las instancias del laboratorio se detienen y vuelven a arrancar.

## Entorno local

### Servicios

| Servicio | Imagen | Qué hace | Salud |
| --- | --- | --- | --- |
| `db` | `postgis/postgis:16-3.4` | PostgreSQL 16 + PostGIS, mismo motor que RDS. Datos en el volumen `cofrial_db-data` | `pg_isready` |
| `web` | `cofrial/web:dev` (este Dockerfile) | PHP-FPM con el código de `apps/web` montado | `/health` pedido a PHP-FPM: sano solo si llega a la base |
| `worker` | `cofrial/web:dev` | `php artisan queue:work` (colas en la base) | — |
| `nginx` | `nginx:1.30.5-alpine` | Publica la aplicación en `http://localhost:8080` y `/bi` | `/health` a través de Nginx |
| `grafana` | `grafana/grafana:13.2.5` | Grafana OSS en `/bi`, con su estado en la base `grafana` | `/bi/api/health` |

Los puertos solo se abren en `127.0.0.1`: la aplicación en `8080` y PostgreSQL en `5432`. Si ya tienes algo en esos puertos, exporta `COFRIAL_WEB_PUERTO_LOCAL` o `COFRIAL_DB_PUERTO_LOCAL` antes de levantar.

### Paso a paso

Todos los comandos se ejecutan desde la raíz del repositorio.

1. Copia la configuración. Los valores por defecto sirven para local.

   ```bash
   cp .env.example .env
   ```

   Genera la clave de la aplicación (recomendado):

   ```bash
   sed -i "s|^APP_KEY=$|APP_KEY=base64:$(openssl rand -base64 32)|" .env
   ```

   Si la dejas vacía, el contenedor genera una y la guarda en `apps/web/storage/docker-app.key` (ignorada por git), pero solo la ven los procesos del contenedor; los comandos con `docker compose exec` que cifren algo fallarán hasta que la pongas en `.env`.

2. Levanta todo. La primera vez construye la imagen e instala las dependencias de Composer dentro de `web`, así que tarda unos minutos.

   ```bash
   docker compose -f infra/docker/compose.dev.yml up -d --build
   ```

   ```bash
   docker compose -f infra/docker/compose.dev.yml ps
   ```

3. Las migraciones corren solas al arrancar `web` (`COFRIAL_MIGRAR=1`), con el usuario maestro, y después se reaplican permisos y vistas de `db/` ([`db/README.md`](../../db/README.md)); el worker espera a que terminen. Para correrlas a mano después de agregar una nueva:

   ```bash
   docker compose -f infra/docker/compose.dev.yml exec web php artisan migrate --database=pgsql_admin
   ```

   ```bash
   docker compose -f infra/docker/compose.dev.yml exec web sh /var/www/db/aplicar.sh
   ```

4. Siembra los catálogos y los datos de demostración (usuarios y contraseña de demo en [`apps/web/README.md`](../../apps/web/README.md#usuarios-de-demo)):

   ```bash
   docker compose -f infra/docker/compose.dev.yml exec web php artisan db:seed
   ```

5. Abre `http://localhost:8080` (portal), `http://localhost:8080/crm`, `http://localhost:8080/health` y `http://localhost:8080/bi` (Grafana; usuario y contraseña en `GF_SECURITY_ADMIN_*` del `.env`).

6. Detén el entorno. Los datos de la base se conservan en el volumen:

   ```bash
   docker compose -f infra/docker/compose.dev.yml down
   ```

   Para empezar con la base vacía, borra también el volumen (se pierden los datos locales):

   ```bash
   docker compose -f infra/docker/compose.dev.yml down -v
   ```

### Comandos útiles

```bash
docker compose -f infra/docker/compose.dev.yml exec web php artisan test
```

```bash
docker compose -f infra/docker/compose.dev.yml exec db psql -U postgres -d cofrial
```

```bash
docker compose -f infra/docker/compose.dev.yml logs -f web worker
```

### Notas

- Las variables salen del `.env` de la raíz (`env_file`); el contenedor `db` deriva `POSTGRES_USER`, `POSTGRES_PASSWORD` y `POSTGRES_DB` de `DB_ADMIN_USERNAME`, `DB_ADMIN_PASSWORD` y `DB_DATABASE`.
- `db/01-inicializar-dev.sh` solo corre con el volumen vacío. Si cambias contraseñas en `.env` después de la primera vez, recrea el volumen con `down -v`.
- La base `cofrial` pertenece a `postgres`, que migra; la aplicación usa `app_rw` con los mismos permisos que en RDS ([T-005](../../tasks/T-005-roles-base-de-datos/README.md)). Solo `cofrial_test` pertenece a `app_rw`, porque los tests recrean el esquema. Si tu volumen es anterior a T-005, recréalo con `down -v`.
- `web` y `worker` corren con el UID/GID `1000` (o los de `UID`/`GID` si los exportas) para poder escribir en `apps/web/storage` sin cambiar permisos del código montado.
- Si existe `apps/web/.env`, Laravel solo lo usa para variables que el contenedor no define; las del `.env` de la raíz siempre ganan.
- Nginx resuelve `web` y `grafana` con el DNS interno de Docker en cada petición, así que recrear un contenedor no deja la aplicación en 502.
- Los tests (`php artisan test`) usan la base `cofrial_test`, también dentro del contenedor: `phpunit.xml` fuerza su entorno y nunca tocan la base de desarrollo `cofrial`. `db/01-inicializar-dev.sh` crea `cofrial_test` con PostGIS; si tu volumen es anterior a T-003, recréalo con `down -v`.
- En local todos los servicios reciben el `.env` completo. En producción (T-024) cada servicio debe recibir solo sus credenciales.
