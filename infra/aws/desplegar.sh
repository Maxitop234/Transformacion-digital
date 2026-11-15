#!/usr/bin/env bash
# Actualiza las EC2 con la última versión de una rama de GitHub, sin SSH:
# usa Run Command de Systems Manager (rol LabRole) para que cada instancia
# haga git pull y vuelva a ejecutar su script de arranque.
#
#   infra/aws/desplegar.sh web        # las dos EC2 web, de a una
#   infra/aws/desplegar.sh iot        # el nodo IoT
#   infra/aws/desplegar.sh web main   # rama explícita (por defecto, la de despliegue.conf)
#
# Las EC2 web se actualizan una a la vez, en orden de nombre: primero
# cofrial-web-1, que migra y crea los usuarios de la base que necesita la otra.
# Mientras una reconstruye, el balanceador atiende con la otra.
set -euo pipefail

rol="${1:?Uso: desplegar.sh web|iot [rama]}"
rama="${2:-}"
[[ "$rol" == "web" || "$rol" == "iot" ]] || { echo "rol desconocido: $rol" >&2; exit 2; }

instancias="$(aws ec2 describe-instances \
    --filters "Name=tag:cofrial-rol,Values=$rol" "Name=instance-state-name,Values=running" \
    --query 'Reservations[].Instances[].[Tags[?Key==`Name`]|[0].Value, InstanceId]' --output text | sort)"
[[ -n "$instancias" ]] || { echo "No hay instancias $rol en ejecución." >&2; exit 1; }

comando=$(cat <<EOF
set -euo pipefail
. /etc/cofrial/despliegue.conf
rama="${rama:-\$COFRIAL_RAMA}"
cd /opt/cofrial
git fetch --quiet origin "\$rama"
git checkout --quiet -B "\$rama" "origin/\$rama"
echo "cofrial-despliegue: \$(git log -1 --oneline)"
infra/aws/arranque/$rol.sh
EOF
)
parametros="$(python3 -c 'import json, sys; print(json.dumps({"commands": sys.stdin.read().splitlines(), "executionTimeout": ["1800"]}))' <<<"$comando")"

grupo=""
if [[ "$rol" == "web" ]]; then
    grupo="$(aws elbv2 describe-target-groups --names cofrial-web --query 'TargetGroups[0].TargetGroupArn' --output text)"
fi

while read -r nombre id; do
    echo "== $nombre ($id)"
    if [[ -n "$grupo" ]]; then
        # Sin errores para los usuarios: la instancia sale de rotación antes de
        # reconstruirse (el balanceador termina las peticiones en curso, 30 s).
        aws elbv2 deregister-targets --target-group-arn "$grupo" --targets "Id=$id"
        aws elbv2 wait target-deregistered --target-group-arn "$grupo" --targets "Id=$id"
        echo "   fuera del balanceador"
    fi
    envio="$(aws ssm send-command --instance-ids "$id" --document-name AWS-RunShellScript \
        --comment "cofrial desplegar $rol" --parameters "$parametros" \
        --query Command.CommandId --output text)"
    aws ssm wait command-executed --command-id "$envio" --instance-id "$id" 2>/dev/null || true
    # El waiter se rinde a los ~100 s; los despliegues con build tardan más.
    while true; do
        estado="$(aws ssm get-command-invocation --command-id "$envio" --instance-id "$id" --query Status --output text)"
        [[ "$estado" == "Pending" || "$estado" == "InProgress" || "$estado" == "Delayed" ]] || break
        sleep 15
    done
    aws ssm get-command-invocation --command-id "$envio" --instance-id "$id" \
        --query StandardOutputContent --output text | grep -E '^cofrial-' || true
    echo "   estado: $estado"
    if [[ -n "$grupo" ]]; then
        # Vuelve a rotación aunque el despliegue falle: si quedó mal, el
        # chequeo de salud la mantiene fuera igual.
        aws elbv2 register-targets --target-group-arn "$grupo" --targets "Id=$id"
        if [[ "$estado" == "Success" ]]; then
            aws elbv2 wait target-in-service --target-group-arn "$grupo" --targets "Id=$id"
            echo "   de vuelta en el balanceador, sana"
        fi
    fi
    [[ "$estado" == "Success" ]] || exit 1
done <<<"$instancias"
