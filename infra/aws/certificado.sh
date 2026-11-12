#!/usr/bin/env bash
# Genera un certificado autofirmado y lo importa a ACM para el listener HTTPS
# del balanceador (T-024, SECURITY.md §4). Sin dominio propio no hay otra
# opción en el laboratorio: el navegador mostrará un aviso.
#
#   infra/aws/certificado.sh
#
# Guarda el ARN en /cofrial/config/CERTIFICADO_ARN (no es secreto), de donde lo
# lee web.yaml. La clave privada solo existe en un directorio temporal que se
# borra al terminar; no queda en el disco ni en el repositorio.
set -euo pipefail

PARAMETRO=/cofrial/config/CERTIFICADO_ARN

if arn="$(aws ssm get-parameter --name "$PARAMETRO" --query Parameter.Value --output text 2>/dev/null)" \
    && aws acm describe-certificate --certificate-arn "$arn" >/dev/null 2>&1; then
    echo "Ya existe: $arn"
    exit 0
fi

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

# El DNS del balanceador cambia si se recrea: el nombre comodín cubre cualquiera de us-east-1.
openssl req -x509 -newkey rsa:2048 -nodes -days 365 \
    -keyout "$tmp/clave.pem" -out "$tmp/certificado.pem" \
    -subj "/O=Cofrial SpA (prototipo)/CN=*.us-east-1.elb.amazonaws.com" \
    -addext "subjectAltName=DNS:*.us-east-1.elb.amazonaws.com" 2>/dev/null

arn="$(aws acm import-certificate \
    --certificate "fileb://$tmp/certificado.pem" \
    --private-key "fileb://$tmp/clave.pem" \
    --tags Key=proyecto,Value=cofrial Key=Name,Value=cofrial-autofirmado \
    --query CertificateArn --output text)"

aws ssm put-parameter --name "$PARAMETRO" --type String --value "$arn" --overwrite >/dev/null
echo "Importado: $arn"
