<?php

namespace Tests\Feature\Admin;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Comuna;
use App\Models\DetalleListaPrecio;
use App\Models\DetallePedido;
use App\Models\DireccionDespacho;
use App\Models\EstadoPedido;
use App\Models\Inventario;
use App\Models\ListaPrecio;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\RangoConservacion;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Vendedor;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreaUsuarios;
use Tests\TestCase;

/** Mantenedores de administración (T-007). Datos ficticios. */
class MantenedoresTest extends TestCase
{
    use CreaUsuarios;
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->admin = $this->usuario(Rol::ADMINISTRADOR);
    }

    // --- Acceso -----------------------------------------------------------

    public function test_solo_el_administrador_accede_a_los_mantenedores(): void
    {
        $rutas = ['/crm/admin', '/crm/admin/productos', '/crm/admin/productos/nuevo', '/crm/admin/listas-precio',
            '/crm/admin/inventario', '/crm/admin/usuarios', '/crm/admin/usuarios/nuevo'];

        foreach ($rutas as $ruta) {
            $this->actingAs($this->admin)->get($ruta)->assertOk();
            $this->actingAs($this->vendedor())->get($ruta)->assertForbidden();
            $this->actingAs($this->usuario(Rol::DESPACHADOR))->get($ruta)->assertForbidden();
            $this->actingAs($this->usuario(Rol::CLIENTE_B2B))->get($ruta)->assertForbidden();
        }

        $producto = $this->producto();
        $this->actingAs($this->vendedor())
            ->post('/crm/admin/inventario', ['id_producto' => $producto->id_producto, 'tipo' => 'ingreso', 'cantidad' => 5])
            ->assertForbidden();
        $this->actingAs($this->usuario(Rol::DESPACHADOR))
            ->patch("/crm/admin/productos/{$producto->id_producto}", ['nombre' => 'X'])
            ->assertForbidden();

        auth()->logout();
        $this->get('/crm/admin/productos')->assertRedirect('/login');
    }

    // --- Productos --------------------------------------------------------

    public function test_crear_un_producto_crea_su_inventario_en_cero(): void
    {
        $this->actingAs($this->admin)->post('/crm/admin/productos', $this->datosProducto(['sku' => 'TST-001']))
            ->assertRedirect('/crm/admin/productos');

        $producto = Producto::where('sku', 'TST-001')->firstOrFail();
        $this->assertTrue($producto->activo);
        $this->assertSame('0.00', $producto->inventario->stock_disponible);
    }

    public function test_editar_y_desactivar_un_producto(): void
    {
        $producto = $this->producto();
        $otroRango = RangoConservacion::whereKeyNot($producto->id_rango)->firstOrFail();

        $this->actingAs($this->admin)->patch("/crm/admin/productos/{$producto->id_producto}", $this->datosProducto([
            'sku' => $producto->sku,
            'nombre' => 'Nombre corregido',
            'id_rango' => $otroRango->id_rango,
            'activo' => '0',
        ]))->assertRedirect('/crm/admin/productos');

        $producto->refresh();
        $this->assertSame('Nombre corregido', $producto->nombre);
        $this->assertSame($otroRango->id_rango, $producto->id_rango);
        $this->assertFalse($producto->activo);
    }

    public function test_el_sku_es_unico_y_la_unidad_debe_ser_valida(): void
    {
        $existente = $this->producto();

        $this->actingAs($this->admin)->post('/crm/admin/productos', $this->datosProducto(['sku' => $existente->sku]))
            ->assertSessionHasErrors('sku');
        $this->actingAs($this->admin)->post('/crm/admin/productos', $this->datosProducto(['sku' => 'TST-NUEVO', 'unidad_medida' => 'litro']))
            ->assertSessionHasErrors('unidad_medida');
    }

    // --- Listas de precios ------------------------------------------------

    public function test_crear_una_lista_y_fijar_y_quitar_precios(): void
    {
        $a = $this->producto('TST-A');
        $b = $this->producto('TST-B');

        $this->actingAs($this->admin)->post('/crm/admin/listas-precio', [
            'nombre' => 'Convenio de prueba', 'vigente_desde' => '2026-01-01', 'activa' => '1',
        ]);
        $lista = ListaPrecio::where('nombre', 'Convenio de prueba')->firstOrFail();

        $this->actingAs($this->admin)->patch("/crm/admin/listas-precio/{$lista->id_lista_precio}/precios", [
            'precios' => [$a->id_producto => '4990', $b->id_producto => '1250.50'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('4990.00', $this->precio($lista, $a));
        $this->assertSame('1250.50', $this->precio($lista, $b));

        $this->actingAs($this->admin)->patch("/crm/admin/listas-precio/{$lista->id_lista_precio}/precios", [
            'precios' => [$a->id_producto => '5200', $b->id_producto => ''],
        ]);

        $this->assertSame('5200.00', $this->precio($lista, $a));
        $this->assertNull($this->precio($lista, $b));

        $this->actingAs($this->admin)->patch("/crm/admin/listas-precio/{$lista->id_lista_precio}/precios", [
            'precios' => [$a->id_producto => '-1'],
        ])->assertSessionHasErrors();
        $this->assertSame('5200.00', $this->precio($lista, $a));
    }

    public function test_rn01_cambiar_un_precio_de_lista_no_altera_el_precio_pactado_de_pedidos_existentes(): void
    {
        $producto = $this->producto();
        $lista = ListaPrecio::where('nombre', CatalogoSeeder::LISTA_PUBLICA)->firstOrFail();
        DetalleListaPrecio::create(['id_lista_precio' => $lista->id_lista_precio, 'id_producto' => $producto->id_producto, 'precio_unitario' => 3000]);

        $cliente = $this->clienteDe(null);
        $direccion = DireccionDespacho::create([
            'id_cliente' => $cliente->id_cliente, 'id_comuna' => Comuna::first()->id_comuna, 'alias' => 'Bodega', 'calle' => 'Pasaje Demo 1',
        ]);
        $pedido = Pedido::create([
            'id_direccion' => $direccion->id_direccion,
            'id_estado_pedido' => EstadoPedido::where('orden', 1)->value('id_estado_pedido'),
            'canal' => 'portal',
        ]);
        DetallePedido::create(['id_pedido' => $pedido->id_pedido, 'id_producto' => $producto->id_producto, 'cantidad' => 2, 'precio_unitario_pactado' => 3000]);

        $this->actingAs($this->admin)->patch("/crm/admin/listas-precio/{$lista->id_lista_precio}/precios", [
            'precios' => [$producto->id_producto => '3500'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('3500.00', $this->precio($lista, $producto));
        $this->assertSame('3000.00', DetallePedido::where('id_pedido', $pedido->id_pedido)->value('precio_unitario_pactado'));
    }

    // --- Inventario -------------------------------------------------------

    public function test_un_ingreso_aumenta_el_stock_y_deja_su_movimiento_con_el_responsable(): void
    {
        $producto = $this->producto();

        $this->actingAs($this->admin)->post('/crm/admin/inventario', [
            'id_producto' => $producto->id_producto, 'tipo' => 'ingreso', 'cantidad' => '12.5',
        ])->assertRedirect('/crm/admin/inventario')->assertSessionHasNoErrors();

        $this->assertSame('12.50', Inventario::find($producto->id_producto)->stock_disponible);

        $movimiento = MovimientoInventario::where('id_producto', $producto->id_producto)->sole();
        $this->assertSame('ingreso', $movimiento->tipo);
        $this->assertSame('12.50', $movimiento->cantidad);
        $this->assertSame($this->admin->id_usuario, $movimiento->id_usuario);
        $this->assertNull($movimiento->id_pedido);
    }

    public function test_un_ajuste_corrige_con_signo_y_no_baja_de_lo_reservado(): void
    {
        $producto = $this->producto();
        Inventario::whereKey($producto->id_producto)->update(['stock_disponible' => 10, 'stock_reservado' => 4]);

        $this->actingAs($this->admin)->post('/crm/admin/inventario', [
            'id_producto' => $producto->id_producto, 'tipo' => 'ajuste', 'cantidad' => '-5',
        ])->assertSessionHasNoErrors();
        $this->assertSame('5.00', Inventario::find($producto->id_producto)->stock_disponible);

        $this->actingAs($this->admin)->post('/crm/admin/inventario', [
            'id_producto' => $producto->id_producto, 'tipo' => 'ajuste', 'cantidad' => '-2',
        ])->assertSessionHasErrors('cantidad');
        $this->assertSame('5.00', Inventario::find($producto->id_producto)->stock_disponible);

        $this->assertSame(1, MovimientoInventario::where('id_producto', $producto->id_producto)->count());
    }

    public function test_un_ingreso_negativo_o_cero_y_otros_tipos_se_rechazan(): void
    {
        $producto = $this->producto();

        foreach ([['ingreso', '-3'], ['ingreso', '0'], ['ajuste', '0'], ['merma', '2'], ['reserva', '2']] as [$tipo, $cantidad]) {
            $this->actingAs($this->admin)->post('/crm/admin/inventario', [
                'id_producto' => $producto->id_producto, 'tipo' => $tipo, 'cantidad' => $cantidad,
            ])->assertSessionHasErrors();
        }

        $this->assertSame(0, MovimientoInventario::count());
    }

    // --- Usuarios ---------------------------------------------------------

    public function test_crear_un_vendedor_crea_su_ficha_de_vendedor(): void
    {
        $this->actingAs($this->admin)->post('/crm/admin/usuarios', [
            'email' => 'nuevo.vendedor@prueba.example', 'password' => 'clave-larga-123', 'password_confirmation' => 'clave-larga-123',
            'rol' => Rol::VENDEDOR, 'nombre' => 'Vendedora Nueva',
        ])->assertRedirect('/crm/admin/usuarios');

        $usuario = Usuario::where('email', 'nuevo.vendedor@prueba.example')->firstOrFail();
        $this->assertSame(Rol::VENDEDOR, $usuario->nombreRol());
        $this->assertSame('Vendedora Nueva', $usuario->vendedor->nombre);

        $this->post('/logout');
        $this->post('/login', ['email' => 'nuevo.vendedor@prueba.example', 'password' => 'clave-larga-123'])->assertRedirect('/crm');
    }

    public function test_no_se_crean_cuentas_de_cliente_desde_administracion(): void
    {
        $this->actingAs($this->admin)->post('/crm/admin/usuarios', [
            'email' => 'cliente@prueba.example', 'password' => 'clave-larga-123', 'password_confirmation' => 'clave-larga-123',
            'rol' => Rol::CLIENTE_B2C,
        ])->assertSessionHasErrors('rol');

        $this->assertFalse(Usuario::where('email', 'cliente@prueba.example')->exists());
    }

    public function test_desactivar_un_usuario_le_impide_iniciar_sesion(): void
    {
        $despachador = $this->usuario(Rol::DESPACHADOR);

        $this->actingAs($this->admin)->patch("/crm/admin/usuarios/{$despachador->id_usuario}", [
            'email' => $despachador->email, 'rol' => Rol::DESPACHADOR, 'activo' => '0',
        ])->assertRedirect('/crm/admin/usuarios');

        $this->assertFalse($despachador->refresh()->activo);

        $this->post('/logout');
        $this->post('/login', ['email' => $despachador->email, 'password' => self::CLAVE])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_cambiar_el_rol_de_un_usuario(): void
    {
        $despachador = $this->usuario(Rol::DESPACHADOR);

        $this->actingAs($this->admin)->patch("/crm/admin/usuarios/{$despachador->id_usuario}", [
            'email' => $despachador->email, 'rol' => Rol::VENDEDOR, 'nombre' => 'Ex despachador', 'activo' => '1',
        ])->assertSessionHasNoErrors();

        $despachador->refresh();
        $this->assertSame(Rol::VENDEDOR, $despachador->nombreRol());
        $this->assertTrue($despachador->vendedor->activo);
    }

    public function test_un_vendedor_con_cartera_no_cambia_de_rol_sin_reasignarla(): void
    {
        $vendedor = $this->vendedor();
        $this->clienteDe($vendedor);

        $this->actingAs($this->admin)->patch("/crm/admin/usuarios/{$vendedor->id_usuario}", [
            'email' => $vendedor->email, 'rol' => Rol::DESPACHADOR, 'activo' => '1',
        ])->assertSessionHasErrors('rol');

        $this->assertSame(Rol::VENDEDOR, $vendedor->refresh()->nombreRol());
    }

    public function test_el_administrador_no_se_desactiva_a_si_mismo(): void
    {
        $this->actingAs($this->admin)->patch("/crm/admin/usuarios/{$this->admin->id_usuario}", [
            'email' => $this->admin->email, 'rol' => Rol::ADMINISTRADOR, 'activo' => '0',
        ])->assertSessionHasErrors('activo');

        $this->assertTrue($this->admin->refresh()->activo);
    }

    public function test_el_usuario_sistema_no_se_administra(): void
    {
        $sistema = Usuario::where('email', CatalogoSeeder::EMAIL_SISTEMA)->firstOrFail();

        $this->actingAs($this->admin)->get('/crm/admin/usuarios')->assertDontSee(CatalogoSeeder::EMAIL_SISTEMA);
        $this->actingAs($this->admin)->get("/crm/admin/usuarios/{$sistema->id_usuario}")->assertNotFound();
        $this->actingAs($this->admin)->patch("/crm/admin/usuarios/{$sistema->id_usuario}", [
            'email' => $sistema->email, 'rol' => Rol::ADMINISTRADOR, 'activo' => '1',
        ])->assertNotFound();
    }

    public function test_rn10_reasignar_la_cartera_de_un_vendedor(): void
    {
        $origen = $this->vendedor('Vendedor Origen');
        $destino = $this->vendedor('Vendedor Destino');
        $clientes = [$this->clienteDe($origen, 'Empresa Uno'), $this->clienteDe($origen, 'Empresa Dos')];
        $ajeno = $this->clienteDe($destino, 'Empresa Ajena');

        $this->actingAs($this->admin)->patch("/crm/admin/usuarios/{$origen->id_usuario}/cartera", [
            'id_vendedor_destino' => $destino->vendedor->id_vendedor,
        ])->assertSessionHasNoErrors();

        foreach ($clientes as $cliente) {
            $this->assertSame($destino->vendedor->id_vendedor, $cliente->refresh()->id_vendedor);
        }
        $this->assertSame($destino->vendedor->id_vendedor, $ajeno->refresh()->id_vendedor);
        $this->assertSame(0, Cliente::where('id_vendedor', $origen->vendedor->id_vendedor)->count());

        // Ya sin cartera, el vendedor de origen ve su lista vacía (RN-10).
        $this->actingAs($origen)->get('/crm/clientes')->assertDontSee('Empresa Uno');
        $this->actingAs($destino)->get('/crm/clientes')->assertSee('Empresa Uno')->assertSee('Empresa Dos');
    }

    public function test_no_se_reasigna_la_cartera_a_un_vendedor_inactivo(): void
    {
        $origen = $this->vendedor('Vendedor Origen');
        $inactivo = $this->vendedor('Vendedor Inactivo');
        $inactivo->update(['activo' => false]);
        Vendedor::whereKey($inactivo->vendedor->id_vendedor)->update(['activo' => false]);
        $cliente = $this->clienteDe($origen);

        $this->actingAs($this->admin)->patch("/crm/admin/usuarios/{$origen->id_usuario}/cartera", [
            'id_vendedor_destino' => $inactivo->vendedor->id_vendedor,
        ])->assertSessionHasErrors('id_vendedor_destino');

        $this->assertSame($origen->vendedor->id_vendedor, $cliente->refresh()->id_vendedor);
    }

    // --- Apoyo ------------------------------------------------------------

    private function producto(string $sku = 'TST-100'): Producto
    {
        $this->actingAs($this->admin)->post('/crm/admin/productos', $this->datosProducto(['sku' => $sku]));

        return Producto::where('sku', $sku)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function datosProducto(array $cambios = []): array
    {
        return $cambios + [
            'sku' => 'TST-100',
            'nombre' => 'Producto de prueba',
            'id_categoria' => Categoria::value('id_categoria'),
            'id_rango' => RangoConservacion::value('id_rango'),
            'unidad_medida' => 'kg',
            'activo' => '1',
        ];
    }

    private function precio(ListaPrecio $lista, Producto $producto): ?string
    {
        return DetalleListaPrecio::where(['id_lista_precio' => $lista->id_lista_precio, 'id_producto' => $producto->id_producto])
            ->value('precio_unitario');
    }
}
