<?php

namespace Database\Seeders;

use App\Models\Camion;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\ClientesSeeder;
use Database\Seeders\Demo\EquipoSeeder;
use Database\Seeders\Demo\FlotaSeeder;
use Database\Seeders\Demo\HistorialSeeder;
use Database\Seeders\Demo\ProductosSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Datos ficticios para recorrer el sistema en la defensa. Nunca en producción.
 *
 * Requiere CatalogoSeeder. No es idempotente: si ya hay datos de demo no hace
 * nada (para empezar de cero, `php artisan migrate:fresh --seed`).
 */
class DemoSeeder extends Seeder
{
    /** Contraseña de todos los usuarios de demo (apps/web/README.md). Solo local/demo. */
    public const CLAVE_DEMO = 'cofrial-demo';

    /** Semanas de historial sintético. */
    public const SEMANAS_DE_HISTORIAL = 8;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder no se ejecuta en producción: usa CatalogoSeeder.');
        }

        if (Camion::exists()) {
            $this->command?->warn('Ya hay datos de demostración; no se vuelven a crear.');

            return;
        }

        DB::transaction(fn () => $this->call([
            EquipoSeeder::class,
            ProductosSeeder::class,
            FlotaSeeder::class,
            ClientesSeeder::class,
            HistorialSeeder::class,
        ]));
    }

    /** Primer día del historial sintético. */
    public static function inicio(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfDay()->subWeeks(self::SEMANAS_DE_HISTORIAL);
    }

    public static function idUsuarioSistema(): int
    {
        return Usuario::where('email', CatalogoSeeder::EMAIL_SISTEMA)->valueOrFail('id_usuario');
    }
}
