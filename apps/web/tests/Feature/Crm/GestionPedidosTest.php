<?php

namespace Tests\Feature\Crm;

use App\Domain\Ventas\CalendarioEntregas;
use App\Domain\Ventas\TransicionesPedido;
use App\Models\EstadoPedido;
use App\Models\EtapaPipeline;
use App\Models\HistorialEstadoPedido;
use App\Models\HistorialEtapa;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Oportunidad;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreaUsuarios;
use Tests\Support\CreaVentas;
use Tests\TestCase;

/** Gestión de pedidos en el CRM (T-016). Datos ficticios. */
class GestionPedidosTest extends TestCase
{
    use CreaUsuarios;
    use CreaVentas;
    use RefreshDatabase;

    private Usuario $ana;

    private Usuario $beto;

    private Usuario $despachador;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVentas();
        $this->ana = $this->vendedor('Ana Vendedora');
        $this->beto = $this->vendedor('Beto Vendedor');
        $this->despachador = $this->usuario(Rol::DESPACHADOR);
        $this->producto = $this->productoVenta('TST-001', publico: 10000, convenio: 80000, stock: 50, nombre: 'Producto de bodega');
    }

    // --- Listado y detalle ---------------------------------------------------

    public function test_un_pedido_creado_en_el_portal_aparece_en_el_crm_con_su_detalle(): void
    {
        $pedido = $this->pedidoPortal('B2C', $this->ana, cantidad: 3);

        $this->actingAs($this->ana)->get('/crm/pedidos')->assertOk()
            ->assertSee("data-pedido=\"{$pedido->id_pedido}\"", false)
            ->assertSee('Pendiente');

        $this->actingAs($this->ana)->get("/crm/pedidos/{$pedido->id_pedido}")->assertOk()
            ->assertSee('Producto de bodega')
            ->assertSee('data-precio-pactado="10000.00"', false)
            ->assertSee('$35.700') // total con IVA
            ->assertSee('Pedido creado en el portal')
            ->assertSee('Aún sin despacho asignado.');
    }

    public function test_el_listado_filtra_por_estado_canal_fecha_y_cliente_dentro_de_la_cartera(): void
    {
        $portal = $this->pedidoPortal('B2C', $this->ana);
        $telefono = $this->pedidoTelefonico($this->ana);
        $ajeno = $this->pedidoPortal('B2C', $this->beto);
        Pedido::whereKey($portal->id_pedido)->update(['fecha_pedido' => now()->subDays(10)]);
        $portal->direccionDespacho->cliente->update(['nombre_razon_social' => 'Persona Filtrable']);

        $ver = fn (string $query) => $this->actingAs($this->ana)->get('/crm/pedidos'.$query)->assertOk();

        $ver('')->assertSee("data-pedido=\"{$portal->id_pedido}\"", false)->assertSee("data-pedido=\"{$telefono->id_pedido}\"", false)->assertDontSee("data-pedido=\"{$ajeno->id_pedido}\"", false);
        $ver('?canal=crm')->assertSee("data-pedido=\"{$telefono->id_pedido}\"", false)->assertDontSee("data-pedido=\"{$portal->id_pedido}\"", false);
        $ver('?estado=Pendiente&cliente=filtrable')->assertSee("data-pedido=\"{$portal->id_pedido}\"", false)->assertDontSee("data-pedido=\"{$telefono->id_pedido}\"", false);
        $ver('?desde='.now()->subDays(2)->toDateString())->assertDontSee("data-pedido=\"{$portal->id_pedido}\"", false)->assertSee("data-pedido=\"{$telefono->id_pedido}\"", false);
        $ver('?hasta='.now()->subDays(5)->toDateString())->assertSee("data-pedido=\"{$portal->id_pedido}\"", false)->assertDontSee("data-pedido=\"{$telefono->id_pedido}\"", false);

        // RN-10: el pedido ajeno no existe para Ana; el despachador ve todos.
        $this->actingAs($this->ana)->get("/crm/pedidos/{$ajeno->id_pedido}")->assertNotFound();
        $this->actingAs($this->ana)->post("/crm/pedidos/{$ajeno->id_pedido}/estado", ['estado' => 'Cancelado', 'comentario' => 'x'])->assertNotFound();
        $this->actingAs($this->despachador)->get('/crm/pedidos')->assertSee("data-pedido=\"{$ajeno->id_pedido}\"", false);
    }

    // --- Cambio de estado por rol --------------------------------------------

    public function test_el_vendedor_no_puede_pasar_un_pedido_a_en_ruta_y_el_despachador_si(): void
    {
        $pedido = $this->pedidoPortal('B2C', $this->ana);
        $this->pagar($pedido);
        app(TransicionesPedido::class)->cambiar($pedido, EstadoPedido::EN_PREPARACION, $this->despachador);

        // Al vendedor no se le ofrece ni se le permite.
        $this->actingAs($this->ana)->get("/crm/pedidos/{$pedido->id_pedido}")->assertOk()
            ->assertSee('No hay cambios de estado disponibles para tu rol.')
            ->assertDontSee('<option value="En ruta">', false);
        $this->actingAs($this->ana)->post("/crm/pedidos/{$pedido->id_pedido}/estado", ['estado' => EstadoPedido::EN_RUTA])->assertForbidden();
        $this->assertSame(EstadoPedido::EN_PREPARACION, $pedido->refresh()->estadoPedido->nombre);

        // Al despachador sí.
        $this->actingAs($this->despachador)->get("/crm/pedidos/{$pedido->id_pedido}")->assertSee('<option value="En ruta">', false);
        $this->actingAs($this->despachador)->post("/crm/pedidos/{$pedido->id_pedido}/estado", ['estado' => EstadoPedido::EN_RUTA, 'comentario' => 'Salió el camión'])
            ->assertRedirect("/crm/pedidos/{$pedido->id_pedido}");

        $this->assertSame(EstadoPedido::EN_RUTA, $pedido->refresh()->estadoPedido->nombre);
        $ultimo = HistorialEstadoPedido::where('id_pedido', $pedido->id_pedido)->orderByDesc('id_historial')->first();
        $this->assertSame([$this->despachador->id_usuario, 'Salió el camión'], [$ultimo->id_usuario, $ultimo->comentario]);
    }

    public function test_un_pedido_pendiente_no_se_confirma_por_el_cambio_de_estado_generico(): void
    {
        $pedido = $this->pedidoPortal('B2C', $this->ana);

        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->post("/crm/pedidos/{$pedido->id_pedido}/estado", ['estado' => EstadoPedido::CONFIRMADO])
            ->assertSessionHasErrors('estado');
        $this->assertSame(EstadoPedido::PENDIENTE, $pedido->refresh()->estadoPedido->nombre);
    }

    public function test_una_transicion_invalida_desde_el_crm_responde_409(): void
    {
        $pedido = $this->pedidoPortal('B2C', $this->ana);

        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->post("/crm/pedidos/{$pedido->id_pedido}/estado", ['estado' => EstadoPedido::ENTREGADO])
            ->assertStatus(409);
    }

    // --- Orden de compra B2B (RN-05) ------------------------------------------

    public function test_rn05_confirmar_la_orden_de_compra_deja_el_pago_aprobado_y_el_pedido_confirmado(): void
    {
        $pedido = $this->pedidoPortal('B2B', $this->ana, cantidad: 2);

        $this->actingAs($this->ana)->get("/crm/pedidos/{$pedido->id_pedido}")->assertSee('data-confirmar-oc', false)->assertSee('OC-PRUEBA-1');

        $this->actingAs($this->ana)->post("/crm/pedidos/{$pedido->id_pedido}/confirmar-oc", ['comentario' => 'Revisada con finanzas'])
            ->assertRedirect("/crm/pedidos/{$pedido->id_pedido}");

        $this->assertSame(EstadoPedido::CONFIRMADO, $pedido->refresh()->estadoPedido->nombre);
        $this->assertSame(['credito_30d', 'aprobado'], [Pago::sole()->metodo, Pago::sole()->estado]);
        $ultimo = HistorialEstadoPedido::where('id_pedido', $pedido->id_pedido)->orderByDesc('id_historial')->first();
        $this->assertSame([EstadoPedido::CONFIRMADO, $this->ana->id_usuario, 'Orden de compra OC-PRUEBA-1 validada. Revisada con finanzas'], [$ultimo->estadoPedido->nombre, $ultimo->id_usuario, $ultimo->comentario]);

        // Una segunda vez ya no hay OC pendiente.
        $this->actingAs($this->ana)->post("/crm/pedidos/{$pedido->id_pedido}/confirmar-oc")->assertStatus(409);
    }

    public function test_rn05_solo_el_vendedor_de_la_cartera_o_el_administrador_confirman_la_orden_de_compra(): void
    {
        $pedido = $this->pedidoPortal('B2B', $this->ana);

        $this->actingAs($this->beto)->post("/crm/pedidos/{$pedido->id_pedido}/confirmar-oc")->assertNotFound();
        $this->actingAs($this->despachador)->post("/crm/pedidos/{$pedido->id_pedido}/confirmar-oc")->assertForbidden();
        $this->assertSame('pendiente', Pago::sole()->estado);

        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->post("/crm/pedidos/{$pedido->id_pedido}/confirmar-oc")->assertRedirect();
        $this->assertSame('aprobado', Pago::sole()->estado);
    }

    public function test_un_pedido_b2c_no_tiene_orden_de_compra_que_confirmar(): void
    {
        $pedido = $this->pedidoPortal('B2C', $this->ana);

        $this->actingAs($this->ana)->get("/crm/pedidos/{$pedido->id_pedido}")->assertDontSee('data-confirmar-oc', false);
        $this->actingAs($this->ana)->post("/crm/pedidos/{$pedido->id_pedido}/confirmar-oc")->assertStatus(409);
    }

    public function test_confirmar_la_oc_gana_la_oportunidad_en_propuesta_o_negociacion(): void
    {
        $pedido = $this->pedidoPortal('B2B', $this->ana);
        $cliente = $pedido->direccionDespacho->cliente;
        $propuesta = Oportunidad::create([
            'id_cliente' => $cliente->id_cliente,
            'id_vendedor' => $this->ana->vendedor->id_vendedor,
            'id_etapa' => EtapaPipeline::where('nombre', 'Propuesta')->value('id_etapa'),
            'titulo' => 'Convenio de prueba',
            'origen' => 'vendedor',
            'fecha_creacion' => now()->subDays(20),
        ]);

        $this->actingAs($this->ana)->post("/crm/pedidos/{$pedido->id_pedido}/confirmar-oc");

        $propuesta->refresh();
        $this->assertSame('Ganado', $propuesta->etapaPipeline->nombre);
        $this->assertNotNull($propuesta->fecha_cierre);
        $this->assertSame($propuesta->id_oportunidad, $pedido->refresh()->id_oportunidad);
        $this->assertSame(TransicionesPedido::idUsuarioSistema(), HistorialEtapa::where('id_oportunidad', $propuesta->id_oportunidad)->sole()->id_usuario);

        // Una recompra sin oportunidad abierta no crea ni vincula ninguna.
        $recompra = $this->pedidoPortal('B2B', $this->ana, cuenta: $cliente->usuario);
        $this->actingAs($this->ana)->post("/crm/pedidos/{$recompra->id_pedido}/confirmar-oc");
        $this->assertNull($recompra->refresh()->id_oportunidad);
        $this->assertSame(1, Oportunidad::count());
    }

    // --- Cancelación y efectos sobre el stock ---------------------------------

    public function test_cancelar_exige_motivo_y_libera_la_reserva(): void
    {
        $pedido = $this->pedidoPortal('B2C', $this->ana, cantidad: 4);
        $this->pagar($pedido);
        $this->assertSame('4.00', (string) Inventario::find($this->producto->id_producto)->stock_reservado);

        $this->actingAs($this->ana)->get("/crm/pedidos/{$pedido->id_pedido}")->assertSee('Cancelado (requiere motivo)');
        $this->actingAs($this->ana)->post("/crm/pedidos/{$pedido->id_pedido}/estado", ['estado' => EstadoPedido::CANCELADO])
            ->assertSessionHasErrors('comentario');
        $this->assertSame(EstadoPedido::CONFIRMADO, $pedido->refresh()->estadoPedido->nombre);

        $this->actingAs($this->ana)->post("/crm/pedidos/{$pedido->id_pedido}/estado", ['estado' => EstadoPedido::CANCELADO, 'comentario' => 'El cliente cerró el local'])
            ->assertRedirect();

        $this->assertSame(EstadoPedido::CANCELADO, $pedido->refresh()->estadoPedido->nombre);
        $this->assertSame('0.00', (string) Inventario::find($this->producto->id_producto)->stock_reservado);
        $this->assertSame('-4.00', (string) MovimientoInventario::where('tipo', 'liberacion')->sole()->cantidad);
    }

    public function test_entregar_descuenta_el_stock_y_rechazar_lo_da_de_baja_como_merma(): void
    {
        $transiciones = app(TransicionesPedido::class);
        $entregado = $this->pedidoPortal('B2C', $this->ana, cantidad: 2);
        $rechazado = $this->pedidoPortal('B2C', $this->ana, cantidad: 3);

        foreach ([$entregado, $rechazado] as $pedido) {
            $this->pagar($pedido);
            $transiciones->cambiar($pedido, EstadoPedido::EN_PREPARACION, $this->despachador);
            $transiciones->cambiar($pedido, EstadoPedido::EN_RUTA, $this->despachador);
        }
        $this->assertSame(['50.00', '5.00'], $this->stock());

        $transiciones->cambiar($entregado, EstadoPedido::ENTREGADO, $this->despachador, 'Recepción conforme');
        $this->assertSame(['48.00', '3.00'], $this->stock());
        $this->assertSame('-2.00', (string) MovimientoInventario::where('id_pedido', $entregado->id_pedido)->where('tipo', 'salida')->sole()->cantidad);

        // Rechazar exige motivo.
        $this->actingAs($this->despachador)->post("/crm/pedidos/{$rechazado->id_pedido}/estado", ['estado' => EstadoPedido::RECHAZADO])->assertSessionHasErrors('comentario');
        $this->actingAs($this->despachador)->post("/crm/pedidos/{$rechazado->id_pedido}/estado", ['estado' => EstadoPedido::RECHAZADO, 'comentario' => 'Carga a 9 °C']);
        $this->assertSame(['45.00', '0.00'], $this->stock());
        $this->assertSame('-3.00', (string) MovimientoInventario::where('id_pedido', $rechazado->id_pedido)->where('tipo', 'merma')->sole()->cantidad);
    }

    // --- Trazabilidad ---------------------------------------------------------

    public function test_cada_cambio_muestra_quien_cuando_y_por_que(): void
    {
        $pedido = $this->pedidoPortal('B2B', $this->ana);
        $this->actingAs($this->ana)->post("/crm/pedidos/{$pedido->id_pedido}/confirmar-oc");
        $this->actingAs($this->ana)->post("/crm/pedidos/{$pedido->id_pedido}/estado", ['estado' => EstadoPedido::CANCELADO, 'comentario' => 'Cambio de proveedor']);

        $respuesta = $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->get("/crm/pedidos/{$pedido->id_pedido}")->assertOk();

        foreach (HistorialEstadoPedido::where('id_pedido', $pedido->id_pedido)->get() as $paso) {
            $respuesta->assertSee($paso->fecha_hora->setTimezone(config('app.timezone'))->format('d-m-Y H:i'));
        }
        $respuesta->assertSee('Ana Vendedora · Orden de compra OC-PRUEBA-1 validada.')
            ->assertSee('Ana Vendedora · Cambio de proveedor')
            ->assertSeeInOrder(['Pendiente', 'Confirmado', 'Cancelado']);
    }

    // --- Apoyo ---------------------------------------------------------------

    private function pedidoPortal(string $tipo, Usuario $vendedor, int $cantidad = 1, ?Usuario $cuenta = null): Pedido
    {
        $cuenta ??= $this->compradorCon($tipo, $vendedor);
        // B2C: 10.000 c/u (mínimo $20.000 con IVA); B2B: 80.000 c/u (mínimo $150.000 neto).
        $cantidad = max($cantidad, 2);

        $this->actingAs($cuenta)->post('/carrito/items', ['sku' => 'TST-001', 'cantidad' => $cantidad])->assertSessionHasNoErrors();
        $this->actingAs($cuenta)->post('/pedidos', $this->datosCheckout($cuenta))->assertSessionHasNoErrors();

        return Pedido::latest('id_pedido')->firstOrFail();
    }

    private function pedidoTelefonico(Usuario $vendedor): Pedido
    {
        $cliente = $this->compradorCon('B2B', $vendedor)->cliente;
        $this->actingAs($vendedor)->post("/crm/clientes/{$cliente->id_cliente}/pedidos", [
            'lineas' => [['id_producto' => $this->producto->id_producto, 'cantidad' => 2]],
            'id_direccion' => $cliente->direccionesDespacho()->value('id_direccion'),
            'fecha_entrega_solicitada' => app(CalendarioEntregas::class)->primeraFecha()->toDateString(),
            'orden_compra' => 'OC-TEL',
        ])->assertSessionHasNoErrors();

        return Pedido::latest('id_pedido')->firstOrFail();
    }

    private function pagar(Pedido $pedido): void
    {
        $cuenta = $pedido->direccionDespacho->cliente->usuario;
        $this->actingAs($cuenta)->post("/pedidos/{$pedido->id_pedido}/pago", ['metodo' => 'tarjeta', 'resultado' => 'aprobado'])->assertRedirect('/');
    }

    /** @return array{0: string, 1: string} [disponible, reservado] */
    private function stock(): array
    {
        $inventario = Inventario::find($this->producto->id_producto);

        return [(string) $inventario->stock_disponible, (string) $inventario->stock_reservado];
    }
}
