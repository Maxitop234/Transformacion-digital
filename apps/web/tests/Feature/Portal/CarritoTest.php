<?php

namespace Tests\Feature\Portal;

use App\Domain\Ventas\Montos;
use App\Models\Carrito;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DetalleListaPrecio;
use App\Models\Inventario;
use App\Models\ItemCarrito;
use App\Models\ListaPrecio;
use App\Models\Producto;
use App\Models\RangoConservacion;
use App\Models\Rol;
use App\Models\TipoCliente;
use App\Models\Usuario;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreaUsuarios;
use Tests\TestCase;

/** Carrito de compras del portal (T-010). Datos ficticios. */
class CarritoTest extends TestCase
{
    use CreaUsuarios;
    use RefreshDatabase;

    private ListaPrecio $publica;

    private ListaPrecio $convenio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();

        $this->publica = ListaPrecio::where('nombre', CatalogoSeeder::LISTA_PUBLICA)->firstOrFail();
        $this->convenio = ListaPrecio::create(['nombre' => 'Convenio de prueba', 'vigente_desde' => '2026-01-01', 'activa' => true]);
    }

    // --- Agregar, cambiar y quitar -----------------------------------------

    public function test_agregar_dos_veces_el_mismo_producto_suma_cantidades_en_una_sola_fila(): void
    {
        $producto = $this->producto('TST-001', publico: 1000);
        $cliente = $this->cuentaCliente('B2C');

        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 2])->assertRedirect();
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 3])->assertRedirect();

        $items = ItemCarrito::where('id_producto', $producto->id_producto)->get();
        $this->assertCount(1, $items);
        $this->assertSame('5.00', (string) $items->first()->cantidad);
        $this->assertSame(1, Carrito::where('estado', 'activo')->count());
    }

    public function test_agregar_sin_cantidad_agrega_una_unidad_y_vuelve_a_la_pagina_anterior(): void
    {
        $this->producto('TST-001', publico: 1000);
        $cliente = $this->cuentaCliente('B2C');

        $this->actingAs($cliente)->from('/catalogo')->post('/carrito/items', ['sku' => 'TST-001'])
            ->assertRedirect('/catalogo')
            ->assertSessionHas('estado', 'Producto agregado al carrito.');

        $this->assertSame('1.00', (string) ItemCarrito::sole()->cantidad);
    }

    public function test_cambiar_la_cantidad_fija_el_nuevo_valor_y_quitar_borra_la_linea(): void
    {
        $this->producto('TST-001', publico: 1000);
        $this->producto('TST-002', publico: 2000);
        $cliente = $this->cuentaCliente('B2C');
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 2]);
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-002', 'cantidad' => 1]);

        $this->actingAs($cliente)->patch('/carrito/items/TST-001', ['cantidad' => 4])->assertRedirect('/carrito');
        $this->assertSame('4.00', (string) $this->item('TST-001')->cantidad);

        $this->actingAs($cliente)->delete('/carrito/items/TST-002')->assertRedirect('/carrito');
        $this->assertNull($this->item('TST-002'));
        $this->assertNotNull($this->item('TST-001'));
    }

    public function test_cambiar_la_cantidad_de_un_producto_que_no_esta_en_el_carrito_falla(): void
    {
        $this->producto('TST-001', publico: 1000);
        $cliente = $this->cuentaCliente('B2C');

        $this->actingAs($cliente)->patch('/carrito/items/TST-001', ['cantidad' => 2])->assertSessionHasErrors('sku');
        $this->assertSame(0, ItemCarrito::count());
    }

    public function test_la_cantidad_debe_ser_positiva_y_entera_para_productos_por_unidad(): void
    {
        $this->producto('TST-001', publico: 1000);
        $this->producto('TST-KG', publico: 5000, unidad: 'kg');
        $cliente = $this->cuentaCliente('B2C');

        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 0])->assertSessionHasErrors('cantidad');
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => -1])->assertSessionHasErrors('cantidad');
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 1.5])->assertSessionHasErrors('cantidad');
        $this->assertSame(0, ItemCarrito::count());

        // Por kilo se aceptan fracciones.
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-KG', 'cantidad' => 1.25])->assertSessionHasNoErrors();
        $this->assertSame('1.25', (string) $this->item('TST-KG')->cantidad);
    }

    public function test_no_se_agrega_un_producto_inactivo_inexistente_o_sin_precio_en_la_lista(): void
    {
        $this->producto('TST-INA', publico: 1000, activo: false);
        $this->producto('TST-SIN', institucional: 900); // sin precio en «Público general»
        $cliente = $this->cuentaCliente('B2C');

        foreach (['TST-INA', 'TST-SIN', 'NO-EXISTE'] as $sku) {
            $this->actingAs($cliente)->post('/carrito/items', ['sku' => $sku])->assertSessionHasErrors('sku');
        }

        $this->assertSame(0, ItemCarrito::count());
    }

    // --- Stock (RN-04) -----------------------------------------------------

    public function test_rn04_agregar_al_carrito_no_modifica_stock_reservado(): void
    {
        $producto = $this->producto('TST-001', publico: 1000, stock: 10, reservado: 3);
        $cliente = $this->cuentaCliente('B2C');

        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 4]);
        $this->actingAs($cliente)->patch('/carrito/items/TST-001', ['cantidad' => 6]);

        $inventario = Inventario::find($producto->id_producto);
        $this->assertSame('3.00', (string) $inventario->stock_reservado);
        $this->assertSame('10.00', (string) $inventario->stock_disponible);
        $this->assertSame('6.00', (string) $this->item('TST-001')->cantidad);
    }

    public function test_rn04_si_la_cantidad_supera_el_disponible_para_venta_se_ajusta_y_se_avisa(): void
    {
        // Disponible para venta = 10 − 3 = 7.
        $this->producto('TST-001', publico: 1000, stock: 10, reservado: 3);
        $cliente = $this->cuentaCliente('B2C');

        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 5]);
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 5])
            ->assertSessionHas('aviso', fn (string $aviso) => str_contains($aviso, 'pediste 10') && str_contains($aviso, 'solo hay 7'));
        $this->assertSame('7.00', (string) $this->item('TST-001')->cantidad);

        $this->actingAs($cliente)->patch('/carrito/items/TST-001', ['cantidad' => 50])->assertSessionHas('aviso');
        $this->assertSame('7.00', (string) $this->item('TST-001')->cantidad);

        $this->actingAs($cliente)->patch('/carrito/items/TST-001', ['cantidad' => 2])->assertSessionMissing('aviso');
        $this->assertSame('2.00', (string) $this->item('TST-001')->cantidad);
    }

    public function test_rn04_un_producto_sin_disponible_para_venta_no_se_agrega(): void
    {
        $this->producto('TST-001', publico: 1000, stock: 5, reservado: 5);
        $cliente = $this->cuentaCliente('B2C');

        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001'])->assertSessionHasErrors('sku');
        $this->assertSame(0, ItemCarrito::count());
    }

    public function test_rn04_el_carrito_avisa_si_otro_pedido_reservo_el_stock_despues_de_agregar(): void
    {
        $producto = $this->producto('TST-001', publico: 1000, stock: 10);
        $cliente = $this->cuentaCliente('B2C');
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 8]);

        Inventario::whereKey($producto->id_producto)->update(['stock_reservado' => 6]);

        $this->actingAs($cliente)->get('/carrito')->assertOk()->assertSee('data-excede', false)->assertSee('Hoy quedan 4');
    }

    // --- Precios y totales (RN-01) -----------------------------------------

    public function test_rn01_el_total_del_carrito_cambia_si_cambia_el_precio_de_la_lista(): void
    {
        $producto = $this->producto('TST-001', publico: 1000);
        $cliente = $this->cuentaCliente('B2C');
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 3]);

        // 3 × 1.000 = 3.000 neto; IVA 570; total 3.570.
        $this->actingAs($cliente)->get('/carrito')
            ->assertOk()->assertSee('data-neto="3000"', false)->assertSee('data-iva="570"', false)->assertSee('data-total="3570"', false);

        DetalleListaPrecio::where('id_lista_precio', $this->publica->id_lista_precio)
            ->where('id_producto', $producto->id_producto)
            ->update(['precio_unitario' => 1200]);

        // 3 × 1.200 = 3.600 neto; IVA 684; total 4.284. El carrito no guardó el precio anterior.
        $this->actingAs($cliente)->get('/carrito')
            ->assertOk()->assertSee('data-neto="3600"', false)->assertSee('data-iva="684"', false)->assertSee('data-total="4284"', false);
        $this->assertSame(['id_carrito', 'id_producto', 'cantidad'], array_keys(ItemCarrito::sole()->getAttributes()));
    }

    public function test_rn01_el_carrito_usa_la_lista_del_cliente_y_muestra_neto_iva_y_total(): void
    {
        $this->producto('TST-001', publico: 10000, institucional: 8000);
        $this->producto('TST-KG', publico: 5990, institucional: 5000, unidad: 'kg');
        $b2b = $this->cuentaCliente('B2B', $this->convenio);
        $b2c = $this->cuentaCliente('B2C');

        foreach ([$b2b, $b2c] as $cuenta) {
            $this->actingAs($cuenta)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 2]);
            $this->actingAs($cuenta)->post('/carrito/items', ['sku' => 'TST-KG', 'cantidad' => 1.5]);
        }

        // B2B con convenio, precios netos: 2 × 8.000 + 1,5 × 5.000 = 23.500; IVA 4.465.
        $this->actingAs($b2b)->get('/carrito')->assertOk()
            ->assertSee('Convenio de prueba')
            ->assertSee('$8.000')
            ->assertSee('data-neto="23500"', false)
            ->assertSee('data-iva="4465"', false)
            ->assertSee('data-total="27965"', false);

        // B2C con «Público general»: 2 × 10.000 + 1,5 × 5.990 = 28.985; IVA 5.507 (5.507,15).
        $this->actingAs($b2c)->get('/carrito')->assertOk()
            ->assertSee('Público general')
            ->assertSee('$11.900') // precio unitario con IVA
            ->assertSee('data-neto="28985"', false)
            ->assertSee('data-iva="5507"', false)
            ->assertSee('data-total="34492"', false);
    }

    public function test_el_subtotal_se_calcula_en_enteros_y_se_redondea_al_peso(): void
    {
        $this->assertSame(8985, Montos::subtotalNeto('5990.00', '1.50'));
        $this->assertSame(3, Montos::subtotalNeto('0.33', '10.00')); // 3,30
        $this->assertSame(4, Montos::subtotalNeto('2.50', '1.40')); // 3,50 → 4
        $this->assertSame(30000, Montos::subtotalNeto('10000.00', '3.00'));
    }

    public function test_un_producto_que_sale_de_la_lista_queda_fuera_de_los_totales(): void
    {
        $retirado = $this->producto('TST-001', publico: 1000);
        $this->producto('TST-002', publico: 2000);
        $cliente = $this->cuentaCliente('B2C');
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001']);
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-002']);

        $retirado->update(['activo' => false]);

        $this->actingAs($cliente)->get('/carrito')->assertOk()
            ->assertSee('Ya no está disponible en tu lista')
            ->assertSee('data-neto="2000"', false);
    }

    // --- Abandono (RN-04) --------------------------------------------------

    public function test_rn04_un_carrito_con_24_horas_sin_actividad_pasa_a_abandonado(): void
    {
        $this->producto('TST-001', publico: 1000);
        $inactivo = $this->cuentaCliente('B2C');
        $reciente = $this->cuentaCliente('B2C');

        $this->travelTo(now()->subHours(24)->subMinute());
        $this->actingAs($inactivo)->post('/carrito/items', ['sku' => 'TST-001']);
        $this->travelBack();

        $this->travelTo(now()->subHours(23));
        $this->actingAs($reciente)->post('/carrito/items', ['sku' => 'TST-001']);
        $this->travelBack();

        $this->artisan('carritos:marcar-abandonados')->expectsOutputToContain('1')->assertSuccessful();

        $this->assertSame('abandonado', $this->carritoDe($inactivo)->estado);
        $this->assertSame('activo', $this->carritoDe($reciente)->estado);

        // El cliente que vuelve empieza un carrito nuevo; el abandonado queda como registro.
        $this->actingAs($inactivo)->get('/carrito')->assertSee('data-carrito-vacio', false);
        $this->actingAs($inactivo)->post('/carrito/items', ['sku' => 'TST-001']);
        $this->assertSame(['abandonado', 'activo'], Carrito::where('id_cliente', $inactivo->cliente->id_cliente)
            ->orderBy('id_carrito')->pluck('estado')->all());
    }

    public function test_rn04_cambiar_el_carrito_renueva_la_actividad(): void
    {
        $this->producto('TST-001', publico: 1000);
        $cliente = $this->cuentaCliente('B2C');

        $this->travelTo(now()->subHours(30));
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-001']);
        $this->travelBack();

        $this->travelTo(now()->subHours(2));
        $this->actingAs($cliente)->patch('/carrito/items/TST-001', ['cantidad' => 2]);
        $this->travelBack();

        $this->artisan('carritos:marcar-abandonados')->assertSuccessful();
        $this->assertSame('activo', $this->carritoDe($cliente)->estado);
    }

    public function test_el_abandono_de_carritos_esta_en_el_planificador(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('carritos:marcar-abandonados')->assertSuccessful();
    }

    // --- Acceso --------------------------------------------------------------

    public function test_un_cliente_no_puede_ver_ni_modificar_el_carrito_de_otro(): void
    {
        $this->producto('TST-001', publico: 1000, nombre: 'Producto de Ana');
        $this->producto('TST-002', publico: 2000, nombre: 'Producto de Beto');
        $ana = $this->cuentaCliente('B2C');
        $beto = $this->cuentaCliente('B2B', $this->convenio);
        $this->actingAs($ana)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => 2]);

        // Beto no ve el carrito de Ana.
        $this->actingAs($beto)->get('/carrito')->assertOk()
            ->assertSee('data-carrito-vacio', false)->assertDontSee('Producto de Ana');

        // Ni puede cambiarlo ni vaciarlo: sus acciones caen sobre su propio carrito.
        $this->actingAs($beto)->patch('/carrito/items/TST-001', ['cantidad' => 9])->assertSessionHasErrors('sku');
        $this->actingAs($beto)->delete('/carrito/items/TST-001');
        $this->assertSame('2.00', (string) $this->item('TST-001', $ana)->cantidad);

        // Tampoco hay forma de nombrar el carrito de otro en la URL.
        $idCarritoAna = $this->carritoDe($ana)->id_carrito;
        $this->actingAs($beto)->get("/carrito/{$idCarritoAna}")->assertNotFound();

        // Ana sigue viendo solo lo suyo.
        $this->actingAs($ana)->get('/carrito')->assertOk()->assertSee('Producto de Ana')->assertDontSee('Producto de Beto');
    }

    public function test_solo_las_cuentas_de_cliente_tienen_carrito(): void
    {
        $this->producto('TST-001', publico: 1000);

        $this->get('/carrito')->assertRedirect('/login');
        $this->post('/carrito/items', ['sku' => 'TST-001'])->assertRedirect('/login');

        foreach ([Rol::ADMINISTRADOR, Rol::VENDEDOR, Rol::DESPACHADOR] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/carrito')->assertForbidden();
            $this->actingAs($this->usuario($rol))->post('/carrito/items', ['sku' => 'TST-001'])->assertForbidden();
        }

        $this->assertSame(0, Carrito::count());
    }

    public function test_rn02_una_empresa_pendiente_de_validacion_no_puede_agregar_al_carrito(): void
    {
        $this->producto('TST-001', publico: 1000, institucional: 800);
        $pendiente = $this->cuentaCliente('B2B', $this->convenio, 'pendiente_validacion');

        $this->actingAs($pendiente)->post('/carrito/items', ['sku' => 'TST-001'])
            ->assertSessionHasErrors(['sku' => 'Tu empresa aún está pendiente de validación: podrás comprar cuando un ejecutivo la valide.']);
        $this->assertSame(0, Carrito::count());
    }

    public function test_el_boton_agregar_del_catalogo_envia_al_carrito(): void
    {
        $this->producto('TST-001', publico: 1000);
        $cliente = $this->cuentaCliente('B2C');

        $this->actingAs($cliente)->get('/catalogo')->assertOk()
            ->assertSee('action="'.route('portal.carrito.items.store').'"', false)
            ->assertSee('data-agregar="TST-001"', false)
            ->assertSee('data-enlace-carrito', false);
        $this->actingAs($cliente)->get('/productos/TST-001')->assertOk()->assertSee('name="cantidad"', false);
    }

    // --- Apoyo ---------------------------------------------------------------

    private function producto(
        string $sku,
        ?int $publico = null,
        ?int $institucional = null,
        string $nombre = 'Producto de prueba',
        int $stock = 100,
        int $reservado = 0,
        bool $activo = true,
        string $unidad = 'un',
    ): Producto {
        $producto = Producto::create([
            'id_categoria' => Categoria::value('id_categoria'),
            'id_rango' => RangoConservacion::value('id_rango'),
            'sku' => $sku,
            'nombre' => $nombre,
            'unidad_medida' => $unidad,
            'activo' => $activo,
        ]);
        Inventario::create(['id_producto' => $producto->id_producto, 'stock_disponible' => $stock, 'stock_reservado' => $reservado]);

        foreach ([[$this->publica, $publico], [$this->convenio, $institucional]] as [$lista, $precio]) {
            if ($precio !== null) {
                DetalleListaPrecio::create(['id_lista_precio' => $lista->id_lista_precio, 'id_producto' => $producto->id_producto, 'precio_unitario' => $precio]);
            }
        }

        return $producto;
    }

    private function cuentaCliente(string $tipo, ?ListaPrecio $lista = null, string $estado = 'activo'): Usuario
    {
        $usuario = $this->usuario($tipo === 'B2B' ? Rol::CLIENTE_B2B : Rol::CLIENTE_B2C);

        Cliente::create([
            'id_usuario' => $usuario->id_usuario,
            'id_tipo_cliente' => TipoCliente::where('codigo', $tipo)->value('id_tipo_cliente'),
            'id_lista_precio' => ($lista ?? $this->publica)->id_lista_precio,
            'nombre_razon_social' => "Cliente {$tipo} de prueba",
            'estado_cliente' => $estado,
        ]);

        return $usuario;
    }

    private function carritoDe(Usuario $usuario): Carrito
    {
        return Carrito::where('id_cliente', $usuario->cliente->id_cliente)->latest('id_carrito')->firstOrFail();
    }

    private function item(string $sku, ?Usuario $de = null): ?ItemCarrito
    {
        return ItemCarrito::where('id_producto', Producto::where('sku', $sku)->value('id_producto'))
            ->when($de, fn ($q) => $q->where('id_carrito', $this->carritoDe($de)->id_carrito))
            ->first();
    }
}
