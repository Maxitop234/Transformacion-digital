#!/usr/bin/env bash
# Deja el nodo IoT listo desde cero o lo actualiza (T-025). Idempotente.
#   sudo /opt/cofrial/infra/aws/arranque/iot.sh
# Solo conoce la contraseña de iot_writer: ni la de app_rw ni la clave de la IA
# (docs/SECURITY.md §7).

dir="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=comun.sh
. "$dir/comun.sh"

cargar_despliegue
# Falla antes de escribir nada si el rol de la instancia no llega a los secretos.
secreto IOT_DB_PASSWORD >/dev/null
instalar_docker

registrar "escribiendo $CONFIG/iot.env"
escribir_env "$CONFIG/iot.env" <<EOF
IOT_DB_HOST=$COFRIAL_DB_HOST
IOT_DB_PORT=5432
IOT_DB_DATABASE=cofrial
IOT_DB_SSLMODE=require
IOT_DB_USERNAME=iot_writer
IOT_DB_PASSWORD=$(secreto IOT_DB_PASSWORD)
EOF

registrar "construyendo y levantando los simuladores"
cd /opt/cofrial
docker compose -f infra/docker/compose.iot.yml --env-file "$CONFIG/iot.env" up -d --build --remove-orphans
docker image prune -f >/dev/null
registrar "listo"
