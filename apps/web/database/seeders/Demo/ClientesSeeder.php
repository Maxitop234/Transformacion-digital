<?php

namespace Database\Seeders\Demo;

use App\Models\Cliente;
use App\Models\Comuna;
use App\Models\ContactoCliente;
use App\Models\DireccionDespacho;
use App\Models\ListaPrecio;
use App\Models\Rol;
use App\Models\TipoCliente;
use App\Models\Usuario;
use App\Models\Vendedor;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Clientes ficticios: personas B2C, empresas B2B validadas, una empresa
 * pendiente de validación (RN-02) y dos prospectos sin cuenta.
 *
 * Los nombres de empresa llevan «Demo» para que no se confundan con
 * instituciones reales.
 */
class ClientesSeeder extends Seeder
{
    /** Desplazamientos de RUT dentro del rango ficticio. */
    private const RUT_PERSONAS = 200_000;

    private const RUT_EMPRESAS = 900_000;

    /** Personas B2C: [nombre, comuna, ¿tiene cuenta?] */
    private const PERSONAS = [
        ['Camila Rojas Soto', 'Temuco', true],
        ['Benjamín Muñoz Silva', 'Padre Las Casas', true],
        ['Isidora Contreras Morales', 'Villarrica', true],
        ['Vicente Torres Araya', 'Valdivia', true],
        ['Florencia Reyes Castillo', 'Temuco', true],
        ['Joaquín Espinoza Flores', 'Pucón', true],
    ];

    /**
     * Empresas B2B: [razón social, lista, vendedor (1-3), estado, comunas de
     * entrega, dominio de correo].
     */
    private const EMPRESAS = [
        ['Clínica Araucaria Demo SpA', 'Hospitales y clínicas 2026', 1, 'activo', ['Temuco', 'Padre Las Casas'], 'clinica-araucaria.example'],
        ['Hospital Comunitario Demo Lago Sur', 'Hospitales y clínicas 2026', 2, 'activo', ['Villarrica', 'Pucón', 'Loncoche'], 'hospital-lagosur.example'],
        ['Casino Industrial Demo Cautín Ltda.', 'Casinos y colegios 2026', 1, 'activo', ['Temuco', 'Lautaro'], 'casino-cautin.example'],
        ['Colegio Demo Bosque Nativo', 'Casinos y colegios 2026', 3, 'activo', ['Pucón'], 'colegio-bosquenativo.example'],
        ['Restaurantes Demo Ribera Ltda.', 'Casinos y colegios 2026', 3, 'activo', ['Valdivia', 'Panguipulli'], 'ribera.example'],
        ['Hotel Demo Volcán Azul SpA', CatalogoSeeder::LISTA_PUBLICA, 2, 'pendiente_validacion', ['Pucón'], 'volcanazul.example'],
    ];

    /** Prospectos sin cuenta: [razón social, vendedor, comuna]. */
    private const PROSPECTOS = [
        ['Residencia Demo Los Robles', 1, 'Angol'],
        ['Supermercado Demo Calafate', 2, 'Osorno'],
    ];

    /** Ventanas de recepción típicas (RN-06). */
    private const VENTANAS = [['07:00', '11:00'], ['08:00', '12:00'], ['14:00', '18:00'], ['09:00', '13:00']];

    /** Coordenadas aproximadas de cada comuna para ubicar los puntos de entrega. */
    private const COORDENADAS = [
        'Temuco' => [-38.7359, -72.5904], 'Padre Las Casas' => [-38.7660, -72.5970], 'Villarrica' => [-39.2800, -72.2270],
        'Pucón' => [-39.2720, -71.9770], 'Loncoche' => [-39.3680, -72.6310], 'Lautaro' => [-38.5300, -72.4350],
        'Valdivia' => [-39.8142, -73.2459], 'Panguipulli' => [-39.6440, -72.3330], 'Angol' => [-37.7980, -72.7080],
        'Osorno' => [-40.5740, -73.1330],
    ];

    private Azar $azar;

    private int $telefono = 2000;

    public function run(): void
    {
        $this->azar = new Azar(7);
        $tipos = TipoCliente::pluck('id_tipo_cliente', 'codigo');
        $listas = ListaPrecio::pluck('id_lista_precio', 'nombre');
        $vendedores = Vendedor::orderBy('id_vendedor')->pluck('id_vendedor')->values();
        $roles = Rol::pluck('id_rol', 'nombre');
        $alta = DemoSeeder::inicio()->subDays(30);

        foreach (self::PERSONAS as $i => [$nombre, $comuna, $conCuenta]) {
            $usuario = $conCuenta ? Usuario::create([
                'id_rol' => $roles['cliente_b2c'],
                'email' => Ficticio::correo($nombre),
                'password_hash' => DemoSeeder::CLAVE_DEMO,
                'creado_en' => $alta,
            ]) : null;

            $cliente = Cliente::create([
                'id_usuario' => $usuario?->id_usuario,
                'id_tipo_cliente' => $tipos['B2C'],
                'id_lista_precio' => $listas[CatalogoSeeder::LISTA_PUBLICA],
                'rut' => Ficticio::rut(self::RUT_PERSONAS + $i + 1),
                'nombre_razon_social' => $nombre,
                'estado_cliente' => 'activo',
                'fecha_registro' => $alta->addDays($i),
            ]);

            $this->direccion($cliente, $comuna, 'Casa', null);
        }

        foreach (self::EMPRESAS as $i => [$razonSocial, $lista, $vendedor, $estado, $comunas, $dominio]) {
            $validada = $estado === 'activo';
            $registro = $validada ? $alta->subDays(30 - $i) : DemoSeeder::inicio()->addDays(55);
            $nombreContacto = Ficticio::nombre($this->azar);

            $usuario = Usuario::create([
                'id_rol' => $roles['cliente_b2b'],
                'email' => 'compras@'.$dominio,
                'password_hash' => DemoSeeder::CLAVE_DEMO,
                'creado_en' => $registro,
            ]);

            $cliente = Cliente::create([
                'id_usuario' => $usuario->id_usuario,
                'id_tipo_cliente' => $tipos['B2B'],
                'id_lista_precio' => $listas[$lista],
                'id_vendedor' => $vendedores[$vendedor - 1],
                'rut' => Ficticio::rut(self::RUT_EMPRESAS + $i + 1),
                'nombre_razon_social' => $razonSocial,
                'estado_cliente' => $estado,
                'fecha_registro' => $registro,
                'fecha_validacion' => $validada ? $registro->addDay() : null,
            ]);

            $this->contacto($cliente, $nombreContacto, 'Encargado de compras', 'compras@'.$dominio, true);

            if ($validada && $this->azar->probabilidad(0.6)) {
                $this->contacto($cliente, Ficticio::nombre($this->azar), 'Jefe de cocina', 'cocina@'.$dominio, false);
            }

            foreach ($comunas as $n => $comuna) {
                $this->direccion($cliente, $comuna, $n === 0 ? 'Bodega central' : "Sucursal {$comuna}", $this->azar->uno(self::VENTANAS));
            }
        }

        foreach (self::PROSPECTOS as $i => [$razonSocial, $vendedor, $comuna]) {
            $cliente = Cliente::create([
                'id_tipo_cliente' => $tipos['B2B'],
                'id_lista_precio' => $listas[CatalogoSeeder::LISTA_PUBLICA],
                'id_vendedor' => $vendedores[$vendedor - 1],
                'nombre_razon_social' => $razonSocial,
                'estado_cliente' => 'prospecto',
                'fecha_registro' => $alta->addDays(10 + $i * 7),
            ]);

            $this->contacto($cliente, Ficticio::nombre($this->azar), 'Administración', 'contacto@prospecto'.($i + 1).'.example', true);
            $this->direccion($cliente, $comuna, 'Local principal', null);
        }
    }

    private function contacto(Cliente $cliente, string $nombre, string $cargo, string $email, bool $principal): void
    {
        ContactoCliente::create([
            'id_cliente' => $cliente->id_cliente,
            'nombre' => $nombre,
            'cargo' => $cargo,
            'email' => $email,
            'telefono' => Ficticio::telefono($this->telefono++),
            'es_principal' => $principal,
        ]);
    }

    /**
     * @param  array{0: string, 1: string}|null  $ventana
     */
    private function direccion(Cliente $cliente, string $comuna, string $alias, ?array $ventana): void
    {
        [$lat, $long] = self::COORDENADAS[$comuna];

        DireccionDespacho::create([
            'id_cliente' => $cliente->id_cliente,
            'id_comuna' => Comuna::where('nombre', $comuna)->value('id_comuna'),
            'alias' => $alias,
            // Calles inventadas: no son direcciones reales.
            'calle' => 'Pasaje Demo '.$this->azar->entero(100, 2999),
            'referencia' => $ventana ? 'Recepción por andén de carga' : null,
            'lat' => round($lat + $this->azar->entero(-150, 150) / 10000, 6),
            'long' => round($long + $this->azar->entero(-150, 150) / 10000, 6),
            'ventana_inicio' => $ventana[0] ?? null,
            'ventana_fin' => $ventana[1] ?? null,
        ]);
    }
}
