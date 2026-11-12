#!/usr/bin/env bash
# Comprueba en AWS los criterios de seguridad de la red (T-022).
# Uso: infra/aws/verificar-red.sh [nombre-del-stack]   (por defecto cofrial-red)
# Solo lee; no cambia nada. Sale con código 1 si algún criterio falla.
set -euo pipefail

STACK="${1:-cofrial-red}"
fallas=0

salida() {
    aws cloudformation describe-stacks --stack-name "$STACK" \
        --query "Stacks[0].Outputs[?OutputKey=='$1'].OutputValue" --output text
}

revisar() {
    local descripcion="$1" resultado="$2"
    if [[ -z "$resultado" || "$resultado" == "None" ]]; then
        echo "  OK    $descripcion"
    else
        echo "  FALLA $descripcion"
        echo "$resultado" | sed 's/^/          /'
        fallas=$((fallas + 1))
    fi
}

VPC="$(salida VpcId)"
SG_WEB="$(salida SgWeb)"
SG_IOT="$(salida SgIot)"
SG_DB="$(salida SgDb)"

echo "Stack $STACK · VPC $VPC"
echo
echo "Subredes:"
aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC" \
    --query 'Subnets[].[Tags[?Key==`Name`]|[0].Value, CidrBlock, AvailabilityZone]' --output text | sort | sed 's/^/  /'

echo
echo "Rutas:"
for tabla in cofrial-rt-publica cofrial-rt-datos; do
    rutas="$(aws ec2 describe-route-tables --filters "Name=vpc-id,Values=$VPC" "Name=tag:Name,Values=$tabla" \
        --query 'RouteTables[0].Routes[].join(` → `, [DestinationCidrBlock, GatewayId])' --output text)"
    echo "  $tabla: $rutas"
done

echo
echo "Criterios:"

revisar "rt-datos no tiene ninguna ruta además de la local" \
    "$(aws ec2 describe-route-tables --filters "Name=vpc-id,Values=$VPC" "Name=tag:Name,Values=cofrial-rt-datos" \
        --query 'RouteTables[0].Routes[?DestinationCidrBlock!=`10.20.0.0/16`].[DestinationCidrBlock, GatewayId]' --output text)"

revisar "las subredes de datos están asociadas a rt-datos" \
    "$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC" "Name=tag:Name,Values=cofrial-datos-*" \
        --query 'Subnets[].SubnetId' --output text | tr '\t' '\n' | while read -r subred; do
            tabla="$(aws ec2 describe-route-tables --filters "Name=association.subnet-id,Values=$subred" \
                --query 'RouteTables[0].Tags[?Key==`Name`]|[0].Value' --output text)"
            [[ "$tabla" == "cofrial-rt-datos" ]] || echo "$subred usa $tabla"
        done)"

revisar "ningún Security Group expone 22 a 0.0.0.0/0" \
    "$(aws ec2 describe-security-groups --filters "Name=vpc-id,Values=$VPC" \
        --query 'SecurityGroups[].IpPermissions[?(IpProtocol==`-1` || (FromPort<=`22` && ToPort>=`22`)) && contains(IpRanges[].CidrIp, `0.0.0.0/0`)][]' --output text)"

revisar "ningún Security Group expone 5432 a 0.0.0.0/0" \
    "$(aws ec2 describe-security-groups --filters "Name=vpc-id,Values=$VPC" \
        --query 'SecurityGroups[].IpPermissions[?(IpProtocol==`-1` || (FromPort<=`5432` && ToPort>=`5432`)) && contains(IpRanges[].CidrIp, `0.0.0.0/0`)][]' --output text)"

revisar "sg-db solo admite 5432 desde sg-web y sg-iot" \
    "$(aws ec2 describe-security-groups --group-ids "$SG_DB" \
        --query 'SecurityGroups[0].IpPermissions[].[FromPort, length(IpRanges), length(Ipv6Ranges), length(PrefixListIds), UserIdGroupPairs[].GroupId]' --output json \
        | python3 -c '
import json, sys
reglas, permitidos = json.load(sys.stdin), set(sys.argv[1:])
for puerto, ipv4, ipv6, listas, grupos in reglas:
    if puerto != 5432 or ipv4 or ipv6 or listas or not set(grupos) <= permitidos:
        print(f"regla no permitida en el puerto {puerto}: {ipv4 + ipv6 + listas} rango(s) de IP o listas de prefijos")
if sorted(g for r in reglas for g in r[4]) != sorted(permitidos):
    print("faltan o sobran orígenes en 5432")
' "$SG_WEB" "$SG_IOT")"

echo
if (( fallas > 0 )); then
    echo "$fallas criterio(s) fallaron."
    exit 1
fi
echo "Todos los criterios se cumplen."
