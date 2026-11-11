<?php

namespace Tests\Feature\Portal;

use App\Domain\Ventas\CalendarioEntregas;
use App\Domain\Ventas\TransicionesPedido;
use App\Domain\Ventas\TransicionInvalida;
use App\Models\Carrito;
use App\Models\DetalleListaPrecio;
use App\Models\DetallePedido;
use App\Models\EstadoPedido;
use App\Models\HistorialEstadoPedido;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreaUsuarios;
use Tests\Support\CreaVentas;
use Tests\TestCase;

/** Checkout, pedido y pago (T-011). Datos ficticios. */
class PedidoTest extends TestCase
{
    use CreaUsuarios;
    use CreaVentas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVentas();
    }

    // --- Pedido mínimo (RN-03) ---------------------------------------------

    public function test_rn03_no_se_puede_confirmar_un_pedido_b2c_bajo_el_minimo(): void
    {
        // 16.000 neto = 19.040 con IVA, bajo los $20.000 del mínimo B2C.
        $this->productoVenta('TST-001', publico: 8000);
        $cliente = $this->compradorCon('B2C');
        $this->alCarrito($cliente, 'TST-001', 2);

        $this->actingAs($cliente)->get('/checkout')->assertOk()
            ->assertSee('El pedido mínimo es $20.000 con IVA.')
            ->assertSee('data-confirmar disabled', false);

        $this->actingAs($cliente)->post('/pedidos', $this->datosCheckout($cliente))
            ->assertSessionHasErrors(['pedido' => 'El pedido mínimo es $20.000 con IVA.']);
        $this->assertSame(0, Pedido::count());

        // 3 × 8.000 = 24.000 neto (28.560 con IVA): ahora sí.
        $this->alCarrito($cliente, 'TST-001', 1);
        $this->actingAs($cliente)->post('/pedidos', $this->datosCheckout($cliente))->assertSessionHasNoErrors();
        $this->assertSame(1, Pedido::count());
    }

    public function test_rn03_no_se_puede_confirmar_un_pedido_b2b_bajo_el_minimo_neto(): void
    {
        // 140.000 neto (166.600 con IVA): el mínimo B2B se mide en neto.
        $this->productoVenta('TST-001', convenio: 70000);
        $empresa = $this->compradorCon('B2B');
        $this->alCarrito($empresa, 'TST-001', 2);

        $this->actingAs($empresa)->post('/pedidos', $this->datosCheckout($empresa))
            ->assertSessionHasErrors(['pedido' => 'El pedido mínimo es $150.000 neto.']);
        $this->assertSame(0, Pedido::count());

        $this->alCarrito($empresa, 'TST-001', 1);
        $this->actingAs($empresa)->post('/pedidos', $this->datosCheckout($empresa))->assertSessionHasNoErrors();
        $this->assertSame(1, Pedido::count());
    }

    // --- Creación del pedido (RN-01, RN-04, RN-06, RN-09) --------------------

    public function test_rn01_el_precio_pactado_coincide_con_la_lista_del_cliente_al_comprar(): void
    {
        $producto = $this->productoVenta('TST-001', publico: 25000, convenio: 80000);
        $persona = $this->compradorCon('B2C');
        $empresa = $this->compradorCon('B2B');
        $this->alCarrito($persona, 'TST-001', 1);
        $this->alCarrito($empresa, 'TST-001', 2);

        $this->actingAs($persona)->post('/pedidos', $this->datosCheckout($persona))->assertSessionHasNoErrors();
        $this->actingAs($empresa)->post('/pedidos', $this->datosCheckout($empresa))->assertSessionHasNoErrors();

        $this->assertSame('25000.00', (string) $this->detalleDe($persona)->precio_unitario_pactado);
        $this->assertSame('80000.00', (string) $this->detalleDe($empresa)->precio_unitario_pactado);

        // Si la lista cambia después, el pedido no cambia.
        DetalleListaPrecio::where('id_producto', $producto->id_producto)->update(['precio_unitario' => 99999]);
        $this->assertSame('25000.00', (string) $this->detalleDe($persona)->precio_unitario_pactado);
        $this->assertSame('80000.00', (string) $this->detalleDe($empresa)->precio_unitario_pactado);
    }

    public function test_rn04_crear_el_pedido_reserva_stock_y_registra_todo_en_una_transaccion(): void
    {
        $producto = $this->productoVenta('TST-001', publico: 12000, stock: 10, reservado: 1);
        $cliente = $this->compradorCon('B2C');
        $this->alCarrito($cliente, 'TST-001', 3);
        $fecha = app(CalendarioEntregas::class)->primeraFecha()->toDateString();

        $this->actingAs($cliente)->post('/pedidos', $this->datosCheckout($cliente, ['observaciones' => 'Tocar timbre']))
            ->assertRedirect();

        $pedido = Pedido::sole();
        $this->assertSame(EstadoPedido::PENDIENTE, $pedido->estadoPedido->nombre);
        $this->assertSame('portal', $pedido->canal);
        $this->assertSame($fecha, $pedido->fecha_entrega_solicitada->toDateString());
        $this->assertSame('Tocar timbre', $pedido->observaciones);
        $this->assertSame('4.00', (string) Inventario::find($producto->id_producto)->stock_reservado);

        $movimiento = MovimientoInventario::sole();
        $this->assertSame(['reserva', '3.00', $pedido->id_pedido, $cliente->id_usuario], [$movimiento->tipo, (string) $movimiento->cantidad, $movimiento->id_pedido, $movimiento->id_usuario]);

        $historial = HistorialEstadoPedido::sole();
        $this->assertSame([$pedido->id_pedido, EstadoPedido::idDe(EstadoPedido::PENDIENTE), $cliente->id_usuario], [$historial->id_pedido, $historial->id_estado_pedido, $historial->id_usuario]);

        $carrito = Carrito::sole();
        $this->assertSame('convertido', $carrito->estado);
        $this->assertSame($carrito->id_carrito, $pedido->id_carrito);

        // B2C: redirige a la pasarela; todavía no hay pago.
        $this->assertSame(0, Pago::count());
    }

    public function test_rn04_sin_stock_suficiente_no_se_crea_el_pedido(): void
    {
        $producto = $this->productoVenta('TST-001', publico: 30000, stock: 2);
        $ana = $this->compradorCon('B2C');
        $beto = $this->compradorCon('B2C');
        $this->alCarrito($ana, 'TST-001', 2);
        $this->alCarrito($beto, 'TST-001', 1);

        // Ana compra el último stock; el carrito de Beto quedó sin respaldo.
        $this->actingAs($ana)->post('/pedidos', $this->datosCheckout($ana))->assertSessionHasNoErrors();

        $this->actingAs($beto)->post('/pedidos', $this->datosCheckout($beto))
            ->assertRedirect('/carrito')
            ->assertSessionHasErrors(['pedido' => 'No queda stock suficiente de Producto de prueba.']);
        $this->actingAs($beto)->postJson('/pedidos', $this->datosCheckout($beto))->assertStatus(409);

        $this->assertSame(1, Pedido::count());
        $this->assertSame('2.00', (string) Inventario::find($producto->id_producto)->stock_reservado);
        $this->assertSame('activo', $this->carritoDe($beto)->estado);
    }

    public function test_rn06_la_fecha_de_entrega_parte_el_siguiente_dia_habil_y_el_punto_debe_ser_propio(): void
    {
        $this->productoVenta('TST-001', publico: 30000);
        $cliente = $this->compradorCon('B2C');
        $otro = $this->compradorCon('B2C');
        $this->alCarrito($cliente, 'TST-001', 1);

        $hoy = now(config('app.timezone'))->toDateString();
        $domingo = app(CalendarioEntregas::class)->primeraFecha()->next('Sunday')->toDateString();

        foreach ([$hoy, $domingo] as $fecha) {
            $this->actingAs($cliente)->post('/pedidos', $this->datosCheckout($cliente, ['fecha_entrega_solicitada' => $fecha]))
                ->assertSessionHasErrors('fecha_entrega_solicitada');
        }

        $ajena = $this->datosCheckout($otro)['id_direccion'];
        $this->actingAs($cliente)->post('/pedidos', $this->datosCheckout($cliente, ['id_direccion' => $ajena]))
            ->assertSessionHasErrors('id_direccion');

        $inactiva = $this->direccionDe($cliente->cliente, activa: false);
        $this->actingAs($cliente)->post('/pedidos', $this->datosCheckout($cliente, ['id_direccion' => $inactiva->id_direccion]))
            ->assertSessionHasErrors('id_direccion');

        $this->assertSame(0, Pedido::count());
    }

    public function test_el_checkout_muestra_punto_de_entrega_ventana_y_totales(): void
    {
        $this->productoVenta('TST-001', publico: 10000);
        $cliente = $this->compradorCon('B2C');
        $this->alCarrito($cliente, 'TST-001', 3);

        $this->actingAs($cliente)->get('/checkout')->assertOk()
            ->assertSee('Bodega de prueba')
            ->assertSee('08:00 a 12:00')
            ->assertSee('data-neto="30000"', false)
            ->assertSee('data-iva="5700"', false)
            ->assertSee('data-total="35700"', false)
            ->assertDontSee('data-confirmar disabled', false);

        // Con el carrito vacío vuelve al carrito.
        $this->actingAs($this->compradorCon('B2C'))->get('/checkout')->assertRedirect('/carrito');
    }

    // --- Pago (RN-05) ---------------------------------------------------------

    public function test_rn05_un_pago_aprobado_deja_el_pedido_confirmado_con_su_historial(): void
    {
        $pedido = $this->pedidoB2C($cliente);

        $this->actingAs($cliente)->get("/pedidos/{$pedido->id_pedido}/pago")->assertOk()->assertSee('data-total="35700"', false);

        $this->actingAs($cliente)->post("/pedidos/{$pedido->id_pedido}/pago", ['metodo' => 'tarjeta', 'resultado' => 'aprobado'])
            ->assertRedirect('/')
            ->assertSessionHas('estado');

        $pedido->refresh();
        $this->assertSame(EstadoPedido::CONFIRMADO, $pedido->estadoPedido->nombre);

        $pago = Pago::sole();
        $this->assertSame(['tarjeta', 'aprobado', '35700.00'], [$pago->metodo, $pago->estado, (string) $pago->monto]);

        $historial = HistorialEstadoPedido::where('id_pedido', $pedido->id_pedido)->orderBy('id_historial')->get();
        $this->assertSame([EstadoPedido::PENDIENTE, EstadoPedido::CONFIRMADO], $historial->map(fn ($h) => $h->estadoPedido->nombre)->all());
        $this->assertSame(TransicionesPedido::idUsuarioSistema(), $historial->last()->id_usuario);
        $this->assertSame('Pago en línea aprobado', $historial->last()->comentario);
    }

    public function test_rn05_un_pago_rechazado_deja_el_pedido_pendiente_y_permite_reintentar(): void
    {
        $pedido = $this->pedidoB2C($cliente);

        $this->actingAs($cliente)->post("/pedidos/{$pedido->id_pedido}/pago", ['metodo' => 'transferencia', 'resultado' => 'rechazado'])
            ->assertRedirect("/pedidos/{$pedido->id_pedido}/pago")
            ->assertSessionHas('aviso');
        $this->assertSame(EstadoPedido::PENDIENTE, $pedido->refresh()->estadoPedido->nombre);

        $this->actingAs($cliente)->post("/pedidos/{$pedido->id_pedido}/pago", ['metodo' => 'tarjeta', 'resultado' => 'aprobado']);
        $this->assertSame(EstadoPedido::CONFIRMADO, $pedido->refresh()->estadoPedido->nombre);
        $this->assertSame(['rechazado', 'aprobado'], Pago::orderBy('id_pago')->pluck('estado')->all());
    }

    public function test_rn05_un_pedido_b2b_queda_pendiente_con_su_orden_de_compra(): void
    {
        $this->productoVenta('TST-001', convenio: 100000);
        $empresa = $this->compradorCon('B2B');
        $this->alCarrito($empresa, 'TST-001', 2);

        $this->actingAs($empresa)->post('/pedidos', $this->datosCheckout($empresa, ['orden_compra' => '']))
            ->assertSessionHasErrors('orden_compra');

        $this->actingAs($empresa)->post('/pedidos', $this->datosCheckout($empresa, ['orden_compra' => 'OC-4500123']))
            ->assertRedirect('/')
            ->assertSessionHas('estado', fn (string $m) => str_contains($m, 'OC-4500123'));

        $pedido = Pedido::sole();
        $this->assertSame(EstadoPedido::PENDIENTE, $pedido->estadoPedido->nombre);
        $pago = Pago::sole();
        // 200.000 neto + 38.000 IVA.
        $this->assertSame(['credito_30d', 'pendiente', 'OC-4500123', '238000.00'], [$pago->metodo, $pago->estado, $pago->referencia_externa, (string) $pago->monto]);

        // No usa la pasarela de los B2C.
        $this->actingAs($empresa)->get("/pedidos/{$pedido->id_pedido}/pago")->assertForbidden();
    }

    public function test_un_cliente_no_puede_ver_ni_pagar_el_pedido_de_otro(): void
    {
        $pedido = $this->pedidoB2C($cliente);
        $otro = $this->compradorCon('B2C');

        $this->actingAs($otro)->get("/pedidos/{$pedido->id_pedido}/pago")->assertNotFound();
        $this->actingAs($otro)->post("/pedidos/{$pedido->id_pedido}/pago", ['metodo' => 'tarjeta', 'resultado' => 'aprobado'])->assertNotFound();
        $this->assertSame(EstadoPedido::PENDIENTE, $pedido->refresh()->estadoPedido->nombre);
    }

    public function test_pagar_despues_del_plazo_responde_409(): void
    {
        $pedido = $this->pedidoB2C($cliente);

        $this->travel(31)->minutes();

        $this->actingAs($cliente)->post("/pedidos/{$pedido->id_pedido}/pago", ['metodo' => 'tarjeta', 'resultado' => 'aprobado'])
            ->assertStatus(409);
        $this->assertSame(0, Pago::count());
    }

    // --- Cancelación por falta de pago (RN-04) -------------------------------

    public function test_rn04_un_pedido_b2c_sin_pago_se_cancela_a_los_30_minutos_y_libera_la_reserva(): void
    {
        $producto = $this->productoVenta('TST-001', publico: 10000, stock: 20, reservado: 2);
        $cliente = $this->compradorCon('B2C');
        $this->alCarrito($cliente, 'TST-001', 3);
        $this->actingAs($cliente)->post('/pedidos', $this->datosCheckout($cliente));
        $pedido = Pedido::sole();
        $this->assertSame('5.00', (string) Inventario::find($producto->id_producto)->stock_reservado);

        // A los 29 minutos sigue esperando el pago.
        $this->travel(29)->minutes();
        $this->artisan('pedidos:cancelar-sin-pago')->assertSuccessful();
        $this->assertSame(EstadoPedido::PENDIENTE, $pedido->refresh()->estadoPedido->nombre);

        $this->travel(1)->minutes();
        $this->artisan('pedidos:cancelar-sin-pago')->expectsOutputToContain('1')->assertSuccessful();

        $this->assertSame(EstadoPedido::CANCELADO, $pedido->refresh()->estadoPedido->nombre);
        $this->assertSame('2.00', (string) Inventario::find($producto->id_producto)->stock_reservado);

        $liberacion = MovimientoInventario::where('tipo', 'liberacion')->sole();
        $this->assertSame(['-3.00', $pedido->id_pedido], [(string) $liberacion->cantidad, $liberacion->id_pedido]);

        $ultimo = HistorialEstadoPedido::where('id_pedido', $pedido->id_pedido)->orderByDesc('id_historial')->first();
        $this->assertSame([EstadoPedido::CANCELADO, TransicionesPedido::idUsuarioSistema()], [$ultimo->estadoPedido->nombre, $ultimo->id_usuario]);
    }

    public function test_rn04_la_cancelacion_por_tiempo_no_toca_pedidos_pagados_ni_b2b(): void
    {
        $pagado = $this->pedidoB2C($cliente);
        $this->actingAs($cliente)->post("/pedidos/{$pagado->id_pedido}/pago", ['metodo' => 'tarjeta', 'resultado' => 'aprobado']);

        $this->productoVenta('TST-B2B', convenio: 200000);
        $empresa = $this->compradorCon('B2B');
        $this->alCarrito($empresa, 'TST-B2B', 1);
        $this->actingAs($empresa)->post('/pedidos', $this->datosCheckout($empresa));

        $this->travel(2)->hours();
        $this->artisan('pedidos:cancelar-sin-pago')->expectsOutputToContain(': 0')->assertSuccessful();

        $this->assertSame(
            [EstadoPedido::CONFIRMADO, EstadoPedido::PENDIENTE],
            Pedido::orderBy('id_pedido')->get()->map(fn ($p) => $p->estadoPedido->nombre)->all(),
        );
    }

    public function test_la_cancelacion_sin_pago_esta_en_el_planificador(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('pedidos:cancelar-sin-pago')->assertSuccessful();
    }

    // --- Transiciones (BUSINESS_RULES.md §4) ----------------------------------

    public function test_una_transicion_invalida_responde_409(): void
    {
        $pedido = $this->pedidoB2C($cliente);
        $this->actingAs($cliente)->post("/pedidos/{$pedido->id_pedido}/pago", ['metodo' => 'tarjeta', 'resultado' => 'aprobado']);

        // Confirmado → Confirmado no existe: volver a pagar responde 409.
        $this->actingAs($cliente)->post("/pedidos/{$pedido->id_pedido}/pago", ['metodo' => 'tarjeta', 'resultado' => 'aprobado'])
            ->assertStatus(409)
            ->assertSee('El pedido está «Confirmado» y no puede pasar a «Confirmado».');
        $this->actingAs($cliente)->postJson("/pedidos/{$pedido->id_pedido}/pago", ['metodo' => 'tarjeta', 'resultado' => 'aprobado'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El pedido está «Confirmado» y no puede pasar a «Confirmado».');
        $this->assertSame(1, Pago::count());

        // Saltarse estados tampoco: Confirmado → Entregado.
        $this->expectException(TransicionInvalida::class);
        app(TransicionesPedido::class)->cambiar($pedido, EstadoPedido::ENTREGADO, $this->usuario(Rol::ADMINISTRADOR));
    }

    public function test_un_estado_final_no_admite_transiciones_y_no_deja_historial(): void
    {
        $pedido = $this->pedidoB2C($cliente);
        $transiciones = app(TransicionesPedido::class);
        $transiciones->cambiar($pedido, EstadoPedido::CANCELADO, $cliente, 'Ya no lo necesito');

        foreach ([EstadoPedido::PENDIENTE, EstadoPedido::CONFIRMADO, EstadoPedido::CANCELADO] as $destino) {
            try {
                $transiciones->cambiar($pedido, $destino, $this->usuario(Rol::ADMINISTRADOR));
                $this->fail("Cancelado → {$destino} debió rechazarse.");
            } catch (TransicionInvalida) {
                // esperado
            }
        }

        $this->assertSame(2, HistorialEstadoPedido::where('id_pedido', $pedido->id_pedido)->count());
    }

    public function test_la_transicion_valida_el_rol_y_la_cartera(): void
    {
        $vendedor = $this->vendedor('Vendedor A');
        $ajeno = $this->vendedor('Vendedor B');

        $this->productoVenta('TST-001', convenio: 200000);
        $empresa = $this->compradorCon('B2B', vendedor: $vendedor);
        $this->alCarrito($empresa, 'TST-001', 1);
        $this->actingAs($empresa)->post('/pedidos', $this->datosCheckout($empresa));
        $pedido = Pedido::sole();
        $transiciones = app(TransicionesPedido::class);

        // El cliente no confirma su propio pedido, ni el despachador, ni un vendedor de otra cartera (RN-10).
        foreach ([$empresa, $this->usuario(Rol::DESPACHADOR), $ajeno] as $actor) {
            try {
                $transiciones->cambiar($pedido, EstadoPedido::CONFIRMADO, $actor);
                $this->fail("{$actor->nombreRol()} no debió poder confirmar.");
            } catch (AuthorizationException) {
                // esperado
            }
        }
        $this->assertSame(EstadoPedido::PENDIENTE, $pedido->refresh()->estadoPedido->nombre);

        // Su vendedor sí (validación de la OC, que T-016 lleva al CRM).
        $transiciones->cambiar($pedido, EstadoPedido::CONFIRMADO, $vendedor, 'Orden de compra validada');
        $this->assertSame(EstadoPedido::CONFIRMADO, $pedido->refresh()->estadoPedido->nombre);
    }

    public function test_solo_las_cuentas_de_cliente_hacen_checkout(): void
    {
        $this->get('/checkout')->assertRedirect('/login');
        $this->post('/pedidos', [])->assertRedirect('/login');

        foreach ([Rol::ADMINISTRADOR, Rol::VENDEDOR, Rol::DESPACHADOR] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/checkout')->assertForbidden();
        }
    }

    // --- Apoyo ---------------------------------------------------------------

    private function alCarrito(Usuario $cuenta, string $sku, int $cantidad): void
    {
        $this->actingAs($cuenta)->post('/carrito/items', ['sku' => $sku, 'cantidad' => $cantidad])->assertSessionHasNoErrors();
    }

    /** Pedido B2C pendiente de 3 × $10.000 (total $35.700 con IVA). */
    private function pedidoB2C(?Usuario &$cliente): Pedido
    {
        $this->productoVenta('TST-PAG', publico: 10000, nombre: 'Producto a pagar');
        $cliente = $this->compradorCon('B2C');
        $this->alCarrito($cliente, 'TST-PAG', 3);
        $this->actingAs($cliente)->post('/pedidos', $this->datosCheckout($cliente))->assertSessionHasNoErrors();

        return Pedido::latest('id_pedido')->firstOrFail();
    }

    private function detalleDe(Usuario $cuenta): DetallePedido
    {
        $pedido = Pedido::visiblePara($cuenta)->sole();

        return DetallePedido::where('id_pedido', $pedido->id_pedido)->sole();
    }

    private function carritoDe(Usuario $cuenta): Carrito
    {
        return Carrito::where('id_cliente', $cuenta->cliente->id_cliente)->latest('id_carrito')->firstOrFail();
    }
}
