<?php

namespace Database\Seeders\Demo;

use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Vendedor;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Equipo interno de la demo: un administrador, un despachador y tres
 * vendedores. Credenciales en apps/web/README.md.
 */
class EquipoSeeder extends Seeder
{
    /** email => [rol, nombre del vendedor o null] */
    public const USUARIOS = [
        'admin@cofrial.example' => ['administrador', null],
        'despacho@cofrial.example' => ['despachador', null],
        'vendedor1@cofrial.example' => ['vendedor', 'Antonia Fuentes Araya'],
        'vendedor2@cofrial.example' => ['vendedor', 'Martín Sepúlveda Rojas'],
        'vendedor3@cofrial.example' => ['vendedor', 'Josefa Valenzuela Tapia'],
    ];

    public function run(): void
    {
        $n = 1;

        foreach (self::USUARIOS as $email => [$rol, $nombreVendedor]) {
            $usuario = Usuario::create([
                'id_rol' => Rol::where('nombre', $rol)->value('id_rol'),
                'email' => $email,
                'password_hash' => DemoSeeder::CLAVE_DEMO,
            ]);

            if ($nombreVendedor) {
                Vendedor::create([
                    'id_usuario' => $usuario->id_usuario,
                    'nombre' => $nombreVendedor,
                    'telefono' => Ficticio::telefono($n++),
                ]);
            }
        }
    }
}
