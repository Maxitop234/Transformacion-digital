#!/usr/bin/env bash
# Funciones compartidas por web.sh e iot.sh. Corre como root en Amazon Linux 2023.

set -euo pipefail

export AWS_DEFAULT_REGION="${AWS_DEFAULT_REGION:-us-east-1}"
PREFIJO_SECRETOS=/cofrial/prod
CONFIG=/etc/cofrial

# Versiones fijas para que cada instancia quede igual a la anterior.
COMPOSE_VERSION=v2.29.7
BUILDX_VERSION=v0.17.1

registrar() { echo "cofrial-arranque: $*"; }

instalar_docker() {
    if ! command -v docker >/dev/null; then
        registrar "instalando Docker"
        dnf install -y -q docker git
    fi

    local plugins=/usr/libexec/docker/cli-plugins
    mkdir -p "$plugins"
    if ! docker compose version 2>/dev/null | grep -q "${COMPOSE_VERSION#v}"; then
        registrar "instalando Docker Compose $COMPOSE_VERSION"
        curl -fsSL -o "$plugins/docker-compose" \
            "https://github.com/docker/compose/releases/download/$COMPOSE_VERSION/docker-compose-linux-x86_64"
        chmod 755 "$plugins/docker-compose"
    fi
    if ! docker buildx version 2>/dev/null | grep -q "${BUILDX_VERSION#v}"; then
        registrar "instalando Docker Buildx $BUILDX_VERSION"
        curl -fsSL -o "$plugins/docker-buildx" \
            "https://github.com/docker/buildx/releases/download/$BUILDX_VERSION/buildx-$BUILDX_VERSION.linux-amd64"
        chmod 755 "$plugins/docker-buildx"
    fi

    # Docker arranca con la instancia; los contenedores vuelven solos
    # (restart: unless-stopped) cuando el laboratorio la reinicia.
    systemctl enable --now docker
}

# Lee un secreto de Parameter Store con el rol de la instancia (LabRole).
secreto() {
    aws ssm get-parameter --name "$PREFIJO_SECRETOS/$1" --with-decryption \
        --query Parameter.Value --output text
}

# Escribe un archivo de variables legible solo por root.
escribir_env() {
    local destino="$1"
    install -d -m 700 "$CONFIG"
    umask 077
    cat > "$destino.tmp"
    mv "$destino.tmp" "$destino"
    chmod 600 "$destino"
}

# Configuración no secreta que deja el user data (endpoint de RDS, URL pública…).
cargar_despliegue() {
    # shellcheck source=/dev/null
    . "$CONFIG/despliegue.conf"
}
