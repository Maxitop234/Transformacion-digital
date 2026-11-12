#!/usr/bin/env bash
# Deja una EC2 web lista desde cero o la actualiza (T-024). Idempotente.
# Lo ejecuta el user data la primera vez y infra/aws/desplegar.sh después:
#   sudo /opt/cofrial/infra/aws/arranque/web.sh
# Lee /etc/cofrial/despliegue.conf (COFRIAL_DB_HOST, COFRIAL_URL, COFRIAL_MIGRA)
# y los secretos de /cofrial/prod en Parameter Store; escribe los .env de
# compose.prod.yml en /etc/cofrial, fuera del repositorio.

dir="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=comun.sh
. "$dir/comun.sh"

cargar_despliegue
# Falla antes de escribir nada si el rol de la instancia no llega a los secretos.
secreto APP_KEY >/dev/null
instalar_docker

clave_ia="$(secreto AI_API_KEY)"
[[ "$clave_ia" == "-" ]] && clave_ia=""

registrar "escribiendo $CONFIG/app.env"
escribir_env "$CONFIG/app.env" <<EOF
APP_NAME=Cofrial
APP_ENV=production
APP_KEY=$(secreto APP_KEY)
APP_DEBUG=false
APP_URL=$COFRIAL_URL
APP_TIMEZONE=America/Santiago
APP_LOCALE=es
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=cache
APP_MAINTENANCE_STORE=database
BCRYPT_ROUNDS=12
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_HOST=$COFRIAL_DB_HOST
DB_PORT=5432
DB_DATABASE=cofrial
DB_SSLMODE=require
DB_CONNECT_TIMEOUT=3
DB_USERNAME=app_rw
DB_PASSWORD=$(secreto DB_PASSWORD)
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log
PEDIDO_MINIMO_B2C=20000
PEDIDO_MINIMO_B2B=150000
RESERVA_STOCK_MINUTOS_B2C=30
CARRITO_ABANDONO_HORAS=24
OPORTUNIDAD_SIN_ACTIVIDAD_DIAS=15
CONVERSION_VENTANA_DIAS=90
IVA_PCT=19
AI_ENABLED=true
AI_PROVIDER=groq
AI_API_KEY=$clave_ia
AI_TIMEOUT_SEGUNDOS=15
AI_ASISTENTE_POR_MINUTO=5
AI_ASISTENTE_POR_DIA=50
MAIL_MAILER=log
MAIL_FROM_ADDRESS=no-responder@cofrial.example
MAIL_FROM_NAME=Cofrial
EOF

if [[ "${COFRIAL_MIGRA:-0}" == "1" ]]; then
    registrar "escribiendo $CONFIG/migracion.env (esta instancia migra)"
    escribir_env "$CONFIG/migracion.env" <<EOF
COFRIAL_MIGRAR=1
DB_ADMIN_USERNAME=cofrial_admin
DB_ADMIN_PASSWORD=$(secreto DB_ADMIN_PASSWORD)
IOT_DB_USERNAME=iot_writer
IOT_DB_PASSWORD=$(secreto IOT_DB_PASSWORD)
BI_DB_USERNAME=bi_reader
BI_DB_PASSWORD=$(secreto BI_DB_PASSWORD)
GF_DATABASE_USER=grafana_rw
GF_DATABASE_NAME=grafana
GF_DATABASE_PASSWORD=$(secreto GF_DATABASE_PASSWORD)
EOF
else
    rm -f "$CONFIG/migracion.env"
fi

registrar "escribiendo $CONFIG/grafana.env"
escribir_env "$CONFIG/grafana.env" <<EOF
GF_SECURITY_ADMIN_USER=admin
GF_SECURITY_ADMIN_PASSWORD=$(secreto GF_SECURITY_ADMIN_PASSWORD)
GF_SERVER_ROOT_URL=$COFRIAL_URL/bi
GF_SERVER_SERVE_FROM_SUB_PATH=true
GF_SECURITY_COOKIE_SECURE=true
GF_DATABASE_TYPE=postgres
GF_DATABASE_HOST=$COFRIAL_DB_HOST:5432
GF_DATABASE_NAME=grafana
GF_DATABASE_USER=grafana_rw
GF_DATABASE_PASSWORD=$(secreto GF_DATABASE_PASSWORD)
GF_DATABASE_SSL_MODE=require
DB_HOST=$COFRIAL_DB_HOST
DB_PORT=5432
DB_DATABASE=cofrial
BI_DB_USERNAME=bi_reader
BI_DB_PASSWORD=$(secreto BI_DB_PASSWORD)
EOF

registrar "construyendo y levantando los contenedores"
cd /opt/cofrial
docker compose -f infra/docker/compose.prod.yml up -d --build --remove-orphans
docker image prune -f >/dev/null
registrar "listo"
