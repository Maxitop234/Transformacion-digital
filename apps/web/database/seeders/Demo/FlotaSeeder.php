<?php

namespace Database\Seeders\Demo;

use App\Models\Camion;
use App\Models\Conductor;
use Illuminate\Database\Seeder;

/**
 * Flota: 50 camiones CAM-001 a CAM-050 y un conductor ficticio por camión.
 */
class FlotaSeeder extends Seeder
{
    public const CAMIONES = 50;

    /** Desplazamiento de los RUT de conductores dentro del rango ficticio. */
    private const RUT_CONDUCTORES = 100_000;

    public function run(): void
    {
        $azar = new Azar(4);

        for ($i = 1; $i <= self::CAMIONES; $i++) {
            Camion::create([
                'camion_id' => sprintf('CAM-%03d', $i),
                // Patentes con prefijo ZZ, fuera de las series en uso.
                'patente' => sprintf('ZZ%s%02d', chr(65 + intdiv($i - 1, 26)).chr(65 + ($i - 1) % 26), $i),
                'capacidad_kg' => $azar->uno([3000, 3500, 5000, 8000]),
                // Dos unidades en mantención para que la flota no esté completa.
                'activo' => ! in_array($i, [17, 42], true),
            ]);

            Conductor::create([
                'rut' => Ficticio::rut(self::RUT_CONDUCTORES + $i),
                'nombre' => Ficticio::nombre($azar),
                'telefono' => Ficticio::telefono(1000 + $i),
            ]);
        }
    }
}
