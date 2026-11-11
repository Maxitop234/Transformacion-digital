<?php

namespace Tests\Feature\Portal;

use App\Domain\Catalogo\Precios;
use App\Domain\Ventas\CrearPedido;
use App\Domain\Ventas\DatosPedido;
use App\Domain\Ventas\SinStock;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaUsuarios;
use Tests\Support\CreaVentas;
use Tests\TestCase;

/**
 * RN-04: dos compras simultáneas del último stock no generan sobreventa.
 *
 * Necesita dos conexiones reales a PostgreSQL que vean los mismos datos, así
 * que este test no envuelve los datos en una transacción: los confirma y en
 * `tearDown` vacía las tablas (las deja como tras `migrate:fresh`).
 */
class PedidoConcurrenciaTest extends TestCase
{
    use CreaUsuarios;
    use CreaVentas;
    use RefreshDatabase;

    private const SEGUNDA = 'pgsql_concurrente';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.'.self::SEGUNDA => config('database.connections.pgsql')]);
        $this->prepararVentas();
    }

    protected function tearDown(): void
    {
        DB::purge(self::SEGUNDA);
        $tablas = collect(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = current_schema() AND tablename NOT IN ('migrations', 'spatial_ref_sys')"))
            ->map(fn ($t) => '"'.$t->tablename.'"')
            ->implode(', ');
        DB::statement("TRUNCATE {$tablas} RESTART IDENTITY CASCADE");

        parent::tearDown();
    }

    /** Sin transacción envolvente: la segunda conexión debe ver los datos. */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    public function test_rn04_dos_compras_simultaneas_del_ultimo_stock_no_generan_sobreventa(): void
    {
        $producto = $this->productoVenta('TST-ULT', publico: 30000, stock: 1, nombre: 'Último disponible');
        $ana = $this->compradorCon('B2C');
        $beto = $this->compradorCon('B2C');

        // Los dos tienen la última unidad en su carrito (agregar no reserva).
        foreach ([$ana, $beto] as $cuenta) {
            $this->actingAs($cuenta)->post('/carrito/items', ['sku' => 'TST-ULT'])->assertSessionHasNoErrors();
        }

        // Ana compra y su transacción sigue abierta (aún no confirma).
        DB::beginTransaction();
        $this->comprar($ana);

        // Beto compra a la vez por otra conexión: no lee la reserva sin
        // confirmar de Ana como libre, queda esperando la fila de inventario.
        $esperoAna = false;
        $this->enSegundaConexion(function () use ($beto, &$esperoAna) {
            DB::statement("SET lock_timeout = '500ms'");

            try {
                $this->comprar($beto);
            } catch (QueryException $e) {
                $esperoAna = $e->getCode() === '55P03'; // lock_not_available
            }
        });
        $this->assertTrue($esperoAna, 'La segunda compra debió esperar el bloqueo de la primera.');

        DB::commit();

        // Ya confirmada la compra de Ana, Beto reintenta y recibe «sin stock».
        $sinStock = null;
        $this->enSegundaConexion(function () use ($beto, &$sinStock) {
            try {
                $this->comprar($beto);
            } catch (SinStock $e) {
                $sinStock = $e;
            }
        });

        $this->assertInstanceOf(SinStock::class, $sinStock);
        $this->assertSame(409, $sinStock->getStatusCode());
        $this->assertSame(1, Pedido::count());
        $inventario = Inventario::find($producto->id_producto);
        $this->assertSame(['1.00', '1.00'], [(string) $inventario->stock_disponible, (string) $inventario->stock_reservado]);
    }

    private function comprar(Usuario $cuenta): Pedido
    {
        $datos = $this->datosCheckout($cuenta);

        return app(CrearPedido::class)(
            app(Precios::class)->perfil($cuenta),
            $cuenta,
            new DatosPedido($datos['id_direccion'], $datos['fecha_entrega_solicitada']),
        );
    }

    /** Ejecuta con la segunda conexión como predeterminada (Eloquent y DB::). */
    private function enSegundaConexion(callable $paso): void
    {
        $anterior = DB::getDefaultConnection();
        DB::setDefaultConnection(self::SEGUNDA);

        try {
            $paso();
        } finally {
            DB::setDefaultConnection($anterior);
        }
    }
}
