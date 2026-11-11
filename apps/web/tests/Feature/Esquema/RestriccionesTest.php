<?php

namespace Tests\Feature\Esquema;

use App\Models\Carrito;
use App\Models\ContactoCliente;
use App\Models\DetallePedido;
use App\Models\EtapaPipeline;
use App\Models\Inventario;
use App\Models\ItemCarrito;
use App\Models\Pedido;
use App\Models\Region;
use App\Models\Telemetria;
use App\Models\Ticket;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaDatosBase;
use Tests\TestCase;

/**
 * Integridad declarativa: la base rechaza datos inválidos aunque la
 * aplicación falle (DATA_MODEL.md, criterio de diseño 5).
 */
class RestriccionesTest extends TestCase
{
    use CreaDatosBase;
    use RefreshDatabase;

    private const VIOLA_CHECK = '23514';

    private const VIOLA_UNIQUE = '23505';

    private const VIOLA_FK = '23503';

    private const VIOLA_IDENTITY = '428C9';

    public function test_rechaza_stock_negativo(): void
    {
        $producto = $this->crearProducto();

        $this->assertRechaza(self::VIOLA_CHECK, fn () => Inventario::create([
            'id_producto' => $producto->id_producto,
            'stock_disponible' => -1,
        ]));

        $inventario = $this->crearInventario($producto);

        $this->assertRechaza(self::VIOLA_CHECK, fn () => $inventario->update(['stock_reservado' => -0.5]));
        // Tampoco se puede reservar más de lo que hay (RN-04).
        $this->assertRechaza(self::VIOLA_CHECK, fn () => $inventario->update(['stock_reservado' => 11]));
    }

    public function test_rechaza_combustible_fuera_de_0_a_100(): void
    {
        $camion = $this->crearCamion();
        $lectura = fn (int $combustible) => fn () => Telemetria::create([
            'camion_id' => $camion->camion_id,
            'temp_camara' => 3.5,
            'lat' => -38.73,
            'long' => -72.6,
            'combustible_pct' => $combustible,
        ]);

        $this->assertRechaza(self::VIOLA_CHECK, $lectura(101));
        $this->assertRechaza(self::VIOLA_CHECK, $lectura(-1));

        $lectura(0)();
        $lectura(100)();
        $this->assertSame(2, Telemetria::count());
    }

    public function test_rechaza_cantidad_cero(): void
    {
        $cliente = $this->crearCliente();
        $producto = $this->crearProducto();
        $carrito = $this->crearCarrito($cliente);
        $pedido = $this->crearPedido($cliente);

        $this->assertRechaza(self::VIOLA_CHECK, fn () => ItemCarrito::create([
            'id_carrito' => $carrito->id_carrito,
            'id_producto' => $producto->id_producto,
            'cantidad' => 0,
        ]));

        $this->assertRechaza(self::VIOLA_CHECK, fn () => DetallePedido::create([
            'id_pedido' => $pedido->id_pedido,
            'id_producto' => $producto->id_producto,
            'cantidad' => 0,
            'precio_unitario_pactado' => 1000,
        ]));
    }

    public function test_rechaza_canal_fuera_de_lista(): void
    {
        $pedido = $this->crearPedido();

        foreach (['web', 'telefono', 'whatsapp'] as $canal) {
            $this->assertRechaza(self::VIOLA_CHECK, fn () => Pedido::create([
                'id_direccion' => $pedido->id_direccion,
                'id_estado_pedido' => $pedido->id_estado_pedido,
                'canal' => $canal,
            ]));
        }

        $this->assertSame(1, Pedido::count());
    }

    public function test_un_solo_carrito_activo_por_cliente(): void
    {
        $cliente = $this->crearCliente();
        $carrito = $this->crearCarrito($cliente);

        $this->assertSame('activo', $carrito->refresh()->estado);
        $this->assertRechaza(self::VIOLA_UNIQUE, fn () => $this->crearCarrito($cliente));

        // Uno convertido o abandonado no impide abrir otro.
        $carrito->update(['estado' => 'convertido']);
        $this->crearCarrito($cliente);
        $this->assertSame(2, Carrito::where('id_cliente', $cliente->id_cliente)->count());
    }

    public function test_un_solo_contacto_principal_por_cliente(): void
    {
        $cliente = $this->crearCliente();
        $contacto = fn (bool $principal) => fn () => ContactoCliente::create([
            'id_cliente' => $cliente->id_cliente,
            'nombre' => 'Contacto Ficticio',
            'email' => 'contacto@cofrial.example',
            'es_principal' => $principal,
        ]);

        $contacto(true)();
        $contacto(false)();
        $this->assertRechaza(self::VIOLA_UNIQUE, $contacto(true));
    }

    public function test_etapa_ganada_debe_ser_de_cierre(): void
    {
        $this->assertRechaza(self::VIOLA_CHECK, fn () => EtapaPipeline::create([
            'nombre' => 'Ganado', 'orden' => 6, 'es_cierre' => false, 'es_ganada' => true,
        ]));
    }

    public function test_ticket_nace_sin_clasificar_y_rechaza_categoria_fuera_de_lista(): void
    {
        $cliente = $this->crearCliente();
        $ticket = Ticket::create(['id_cliente' => $cliente->id_cliente, 'asunto' => 'Consulta', 'descripcion' => 'Texto de prueba']);

        $ticket->refresh();
        $this->assertSame('sin_clasificar', $ticket->categoria);
        $this->assertSame('media', $ticket->prioridad);
        $this->assertSame('abierto', $ticket->estado);
        $this->assertFalse($ticket->clasificado_por_ia);

        $this->assertRechaza(self::VIOLA_CHECK, fn () => $ticket->update(['categoria' => 'despacho']));
    }

    public function test_las_claves_foraneas_no_borran_en_cascada(): void
    {
        $cliente = $this->crearCliente();
        $this->crearDireccion($cliente);

        $this->assertRechaza(self::VIOLA_FK, fn () => $cliente->delete());
    }

    public function test_la_base_asigna_los_identificadores(): void
    {
        $this->assertRechaza(self::VIOLA_IDENTITY, fn () => DB::insert('insert into region (id_region, nombre) values (99, ?)', ['Ficticia']));

        $this->assertIsInt(Region::create(['nombre' => 'Los Ríos'])->id_region);
    }

    /**
     * Ejecuta la operación en un savepoint y exige que la base la rechace con
     * el SQLSTATE indicado; la transacción del test sigue usable.
     */
    private function assertRechaza(string $sqlstate, callable $operacion): void
    {
        try {
            DB::transaction(fn () => $operacion());
        } catch (QueryException $e) {
            $this->assertSame($sqlstate, $e->getCode(), $e->getMessage());

            return;
        }

        $this->fail("La base aceptó una operación que debía rechazar (SQLSTATE {$sqlstate}).");
    }
}
