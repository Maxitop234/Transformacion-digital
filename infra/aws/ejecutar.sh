#!/usr/bin/env bash
# Ejecuta un comando dentro de una EC2 de Cofrial con Run Command de Systems
# Manager (sin SSH ni IP pública) y muestra su salida.
#
#   infra/aws/ejecutar.sh cofrial-iot 'docker ps'
#   infra/aws/ejecutar.sh cofrial-web-1 'cd /opt/cofrial && docker compose -f infra/docker/compose.prod.yml ps'
#
# El primer argumento es el nombre (etiqueta Name) de la instancia. El comando
# corre como root en /.
set -euo pipefail

nombre="${1:?Uso: ejecutar.sh <nombre-instancia> '<comando>'}"
comando="${2:?Falta el comando}"

id="$(aws ec2 describe-instances \
    --filters "Name=tag:Name,Values=$nombre" "Name=instance-state-name,Values=running" \
    --query 'Reservations[0].Instances[0].InstanceId' --output text)"
[[ "$id" != "None" ]] || { echo "No hay una instancia $nombre en ejecución." >&2; exit 1; }

parametros="$(python3 -c 'import json, sys; print(json.dumps({"commands": [sys.argv[1]], "executionTimeout": ["900"]}))' "$comando")"
envio="$(aws ssm send-command --instance-ids "$id" --document-name AWS-RunShellScript \
    --comment "cofrial ejecutar" --parameters "$parametros" --query Command.CommandId --output text)"

while true; do
    estado="$(aws ssm get-command-invocation --command-id "$envio" --instance-id "$id" \
        --query Status --output text 2>/dev/null || echo Pending)"
    [[ "$estado" == "Pending" || "$estado" == "InProgress" || "$estado" == "Delayed" ]] || break
    sleep 2
done

aws ssm get-command-invocation --command-id "$envio" --instance-id "$id" \
    --query StandardOutputContent --output text
error="$(aws ssm get-command-invocation --command-id "$envio" --instance-id "$id" \
    --query StandardErrorContent --output text)"
[[ -z "$error" ]] || echo "$error" >&2
[[ "$estado" == "Success" ]]
