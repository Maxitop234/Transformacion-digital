#!/usr/bin/env bash
# Crea en SSM Parameter Store (SecureString, capa estándar sin costo) los
# secretos de producción bajo /cofrial/prod/. Solo crea los que faltan: volver
# a ejecutarlo no cambia contraseñas existentes. Nunca imprime valores.
#
#   infra/aws/secretos.sh
#
# AI_API_KEY se crea con «-» (sin clave: el asistente responde «no disponible»,
# T-021) y se reemplaza a mano con put-parameter --overwrite.
set -euo pipefail

PREFIJO=/cofrial/prod

existe() {
    aws ssm get-parameter --name "$PREFIJO/$1" --query Parameter.Name --output text >/dev/null 2>&1
}

guardar() {
    local nombre="$1" valor="$2"
    if existe "$nombre"; then
        echo "  ya existe  $PREFIJO/$nombre"
        return
    fi
    aws ssm put-parameter --name "$PREFIJO/$nombre" --type SecureString --value "$valor" \
        --tags Key=proyecto,Value=cofrial >/dev/null
    echo "  creado     $PREFIJO/$nombre"
}

# 32 caracteres alfanuméricos: RDS no admite / @ " ni espacios en la contraseña maestra.
clave() {
    python3 -c 'import secrets, string; a = string.ascii_letters + string.digits; print("".join(secrets.choice(a) for _ in range(32)))'
}

echo "Secretos en $PREFIJO:"
for nombre in DB_ADMIN_PASSWORD DB_PASSWORD IOT_DB_PASSWORD BI_DB_PASSWORD GF_DATABASE_PASSWORD GF_SECURITY_ADMIN_PASSWORD; do
    guardar "$nombre" "$(clave)"
done
guardar APP_KEY "base64:$(python3 -c 'import base64, os; print(base64.b64encode(os.urandom(32)).decode())')"

# La clave de la IA la carga la persona responsable (ver infra/aws/README.md).
# Parameter Store no admite valores vacíos: «-» significa «sin clave».
guardar AI_API_KEY "-"
