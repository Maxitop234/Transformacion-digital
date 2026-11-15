#!/usr/bin/env bash
# Carga los datos de demostración en RDS para la defensa (T-027) y cambia la
# contraseña de todas las cuentas de demo por /cofrial/prod/DEMO_PASSWORD.
# Se ejecuta en cofrial-web-1 por Run Command:
#
#   infra/aws/ejecutar.sh cofrial-web-1 /opt/cofrial/infra/aws/cargar-demo.sh
#
# DemoSeeder se niega a correr con APP_ENV=production; aquí corre en un
# contenedor de un solo uso con APP_ENV=demo, sin tocar los que atienden.
# Idempotente: si ya hay camiones, el seeder no crea nada y solo se reaplica
# la contraseña.
set -euo pipefail

dir="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=arranque/comun.sh
. "$dir/arranque/comun.sh"

cd /opt/cofrial
compose=(docker compose -f infra/docker/compose.prod.yml)

registrar "cargando datos de demostración"
"${compose[@]}" run --rm -T --no-deps --entrypoint php -e APP_ENV=demo web \
    artisan db:seed --class=DemoSeeder --force

registrar "aplicando la contraseña de demo de AWS"
CLAVE_DEMO_AWS="$(secreto DEMO_PASSWORD)" "${compose[@]}" exec -T -e CLAVE_DEMO_AWS web \
    php artisan tinker --execute '
        $n = App\Models\Usuario::where("email", "!=", Database\Seeders\CatalogoSeeder::EMAIL_SISTEMA)
            ->get()->each(fn ($u) => $u->update(["password_hash" => getenv("CLAVE_DEMO_AWS")]))->count();
        echo "cuentas actualizadas: $n\n";'
registrar "listo"
