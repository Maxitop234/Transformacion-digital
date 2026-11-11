<?php

namespace Tests\Feature;

use App\Domain\Clientes\Rut;
use App\Models\Camion;
use App\Models\Cliente;
use App\Models\Comuna;
use App\Models\Conductor;
use App\Models\ContactoCliente;
use App\Models\DetalleListaPrecio;
use App\Models\EstadoPedido;
use App\Models\EtapaPipeline;
use App\Models\Inventario;
use App\Models\ListaPrecio;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Region;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_catalogo_es_idempotente(): void
    {
        $this->seed(CatalogoSeeder::class);
        $antes = $this->conteosDeCatalogo();

        $this->seed(CatalogoSeeder::class);

        $this->assertSame($antes, $this->conteosDeCatalogo());
        $this->assertSame(7, $antes['estado_pedido']);
        $this->assertSame(7, $antes['etapa_pipeline']);
        $this->assertSame(5, $antes['rol']);
    }

    public function test_el_usuario_sistema_no_puede_iniciar_sesion(): void
    {
        $this->seed(CatalogoSeeder::class);

        $sistema = Usuario::where('email', CatalogoSeeder::EMAIL_SISTEMA)->firstOrFail();

        $this->assertFalse($sistema->activo);
        $this->assertFalse(Hash::check(DemoSeeder::CLAVE_DEMO, $sistema->password_hash));
    }

    public function test_la_demo_cubre_lo_que_pide_la_tarea(): void
    {
        $this->seed();

        $this->assertSame(50, Camion::count());
        $this->assertSame('CAM-001', Camion::min('camion_id'));
        $this->assertSame('CAM-050', Camion::max('camion_id'));
        $this->assertSame(50, Conductor::count());
        $this->assertGreaterThanOrEqual(30, Producto::count());
        $this->assertSame(Producto::count(), Inventario::count());

        // Una lista pública y al menos dos institucionales con precios distintos.
        $this->assertGreaterThanOrEqual(3, ListaPrecio::count());
        $precios = DetalleListaPrecio::where('id_producto', Producto::min('id_producto'))->pluck('precio_unitario')->unique();
        $this->assertGreaterThanOrEqual(3, $precios->count());

        // Un usuario por rol para la demo.
        foreach (Rol::pluck('nombre', 'id_rol') as $idRol => $rol) {
            $this->assertTrue(Usuario::where('id_rol', $idRol)->where('activo', true)->exists(), "Falta un usuario {$rol}");
        }

        $this->assertTrue(Cliente::where('estado_cliente', 'pendiente_validacion')->exists());
        $this->assertTrue(Cliente::where('estado_cliente', 'prospecto')->whereNull('id_usuario')->exists());
        $this->assertGreaterThan(0, ContactoCliente::where('es_principal', true)->count());

        // Historial de las últimas semanas para el BI.
        $this->assertGreaterThan(50, Pedido::count());
        $this->assertTrue(Pedido::where('fecha_pedido', '<', now()->subWeeks(6))->exists());
        $this->assertSame(2, Pedido::distinct()->count('canal'));
        foreach (['oportunidad', 'historial_etapa', 'interaccion', 'ticket', 'despacho', 'pago'] as $tabla) {
            $this->assertGreaterThan(0, DB::table($tabla)->count(), "Sin datos en {$tabla}");
        }
    }

    public function test_los_rut_de_prueba_son_validos_y_ficticios(): void
    {
        $this->seed();

        $ruts = Cliente::whereNotNull('rut')->pluck('rut')->merge(Conductor::pluck('rut'));
        $this->assertGreaterThan(50, $ruts->count());

        foreach ($ruts as $rut) {
            $this->assertTrue(Rut::esValido($rut), "RUT inválido: {$rut}");
            $numero = Rut::desdeTexto($rut)->numero;
            $this->assertTrue($numero >= 45_000_000 && $numero <= 45_999_999, "RUT fuera del rango ficticio: {$rut}");
        }
    }

    public function test_ningun_contacto_es_real(): void
    {
        $this->seed();

        $correos = Usuario::pluck('email')->merge(ContactoCliente::pluck('email'));
        foreach ($correos as $correo) {
            $this->assertStringEndsWith('.example', $correo, "Correo fuera de un dominio reservado: {$correo}");
        }

        $telefonos = ContactoCliente::pluck('telefono')->merge(Conductor::pluck('telefono'))->filter();
        foreach ($telefonos as $telefono) {
            $this->assertStringStartsWith('+56 9 5555 ', $telefono);
        }

        foreach (Cliente::whereHas('tipoCliente', fn ($q) => $q->where('codigo', 'B2B'))->pluck('nombre_razon_social') as $empresa) {
            $this->assertStringContainsString('Demo', $empresa, "Empresa sin marca de ficticia: {$empresa}");
        }
    }

    public function test_el_historial_es_coherente(): void
    {
        $this->seed();

        // RN-09: el estado actual de cada pedido es el último de su historial.
        $incoherentes = DB::selectOne(<<<'SQL'
            select count(*) as n from pedido p
            where p.id_estado_pedido <> (
                select h.id_estado_pedido from historial_estado_pedido h
                where h.id_pedido = p.id_pedido order by h.fecha_hora desc, h.id_historial desc limit 1)
            SQL)->n;
        $this->assertSame(0, $incoherentes);

        // El inventario cuadra con su bitácora de movimientos.
        $descuadres = DB::selectOne(<<<'SQL'
            select count(*) as n from inventario i
            where i.stock_reservado <> coalesce((select sum(m.cantidad) from movimiento_inventario m
                    where m.id_producto = i.id_producto and m.tipo in ('reserva', 'liberacion')), 0)
               or i.stock_disponible <> (select sum(m.cantidad) from movimiento_inventario m
                    where m.id_producto = i.id_producto and m.tipo in ('ingreso', 'salida', 'merma', 'ajuste'))
            SQL)->n;
        $this->assertSame(0, $descuadres);

        // RN-03: cada pedido cumple el mínimo de su tipo de cliente.
        $bajoMinimo = DB::selectOne(<<<'SQL'
            select count(*) as n from pedido p
            join direccion_despacho d using (id_direccion)
            join cliente c using (id_cliente)
            join tipo_cliente t using (id_tipo_cliente)
            where (select sum(dp.cantidad * dp.precio_unitario_pactado) from detalle_pedido dp where dp.id_pedido = p.id_pedido)
                < case t.codigo when 'B2B' then ?::numeric else ?::numeric end
            SQL, [config('negocio.pedido_minimo.b2b'), config('negocio.pedido_minimo.b2c')])->n;
        $this->assertSame(0, $bajoMinimo);

        // RN-07: un despacho lleva productos de un solo rango de conservación.
        $mezclados = DB::selectOne(<<<'SQL'
            select count(*) as n from (
                select dp.id_despacho from despacho_pedido dp
                join detalle_pedido d on d.id_pedido = dp.id_pedido
                join producto pr on pr.id_producto = d.id_producto
                group by dp.id_despacho having count(distinct pr.id_rango) > 1) x
            SQL)->n;
        $this->assertSame(0, $mezclados);

        $this->assertFalse(DB::table('historial_estado_pedido')->where('fecha_hora', '>', now())->exists());
    }

    public function test_correr_la_demo_dos_veces_no_duplica(): void
    {
        $this->seed();
        $pedidos = Pedido::count();

        $this->seed();

        $this->assertSame($pedidos, Pedido::count());
        $this->assertSame(50, Camion::count());
    }

    public function test_la_demo_no_corre_en_produccion(): void
    {
        $this->seed(CatalogoSeeder::class);
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        // Directo, sin `artisan db:seed`, que en producción pide confirmación.
        $this->app->make(DemoSeeder::class)->run();
    }

    /**
     * @return array<string, int>
     */
    private function conteosDeCatalogo(): array
    {
        return [
            'region' => Region::count(),
            'comuna' => Comuna::count(),
            'rol' => Rol::count(),
            'tipo_cliente' => DB::table('tipo_cliente')->count(),
            'estado_pedido' => EstadoPedido::count(),
            'etapa_pipeline' => EtapaPipeline::count(),
            'categoria' => DB::table('categoria')->count(),
            'rango_conservacion' => DB::table('rango_conservacion')->count(),
            'lista_precio' => ListaPrecio::count(),
            'usuario' => Usuario::count(),
        ];
    }
}
