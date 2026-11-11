# Simulador de telemetría IoT (T-019 · ADR-0014).
#   docker build -t cofrial/iot-simulator apps/iot-simulator            # imagen de ejecución
#   docker build --target test -t cofrial/iot-simulator:test apps/iot-simulator  # tests y formato
FROM python:3.12.16-slim AS base

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1

WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt

# --- Tests: pytest + ruff sobre el mismo intérprete ---------------------------
FROM base AS test
COPY requirements-dev.txt .
RUN pip install -r requirements-dev.txt
COPY . .
CMD ["sh", "-c", "ruff format --check . && ruff check . && pytest"]

# --- Ejecución ----------------------------------------------------------------
FROM base AS runtime
COPY simulador ./simulador
# Sin privilegios dentro del contenedor.
RUN useradd --uid 10001 --no-create-home --shell /usr/sbin/nologin simulador
USER simulador
# Toda la configuración llega por variables de entorno (README.md).
CMD ["python", "-m", "simulador"]
