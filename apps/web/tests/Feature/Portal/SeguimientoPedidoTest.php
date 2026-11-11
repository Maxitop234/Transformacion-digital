<?php

namespace Tests\Feature\Portal;

use App\Models\Alerta;
use App\Models\Camion;
use App\Models\Carrito;
use App\Models\Conductor;
use App\Models\Despacho;
use App\Models\DetalleListaPrecio;
use App\Models\DireccionDespacho;
use App\Models\EstadoPedido;
use App\Models\Inventario;
use App\Models\ItemCarrito;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Telemetria;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreaUsuarios;
use Tests\Support\CreaVentas;
use Tests\TestCase;

/** Seguimiento de pedidos en el portal (T-012). Datos ficticios. */
class SeguimientoPedidoTest extends TestCase
{
    use CreaUsuarios;
    use CreaVentas;
    use RefreshDatabase;

    private const CONDUCTOR_NOMBRE = 'Conductor Reservado Ficticio';

    private const CONDUCTOR_RUT = '45999991-4';

    private const CONDUCTOR_TELEFONO = '+56 9 5555 0199';

    private Usuario $despachador;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVentas();
        $this->despachador = $this->usuario(Rol::DESPACHADOR);
        $this->producto = $this->productoVenta('TST-SEG', publico: 12000, convenio: 80000, stock: 100, nombre: 'Queso de prueba');

        Camion::create(['camion_id' => 'CAM-901', 'patente' => 'ZZ9901', 'capacidad_kg' => 3000]);
        Conductor::create(['rut' => self::CONDUCTOR_RUT, 'nombre' => self::CONDUCTOR_NOMBRE, 'telefono' => self::CONDUCTOR_TELEFONO]);
    }

    // --- Aislamiento entre clientes ------------------------------------------

    public function test_un_cliente_que_abre_un_pedido_ajeno_recibe_404(): void
    {
        $pedidoDeAna = $this->pedidoB2C($ana);
        $beto = $this->compradorCon('B2C');
        $empresa = $this->compradorCon('B2B');

        $this->actingAs($beto)->get("/mis-pedidos/{$pedidoDeAna->id_pedido}")->assertNotFound();
        $this->actingAs($beto)->post("/mis-pedidos/{$pedidoDeAna->id_pedido}/cancelar", ['motivo' => 'No es mío'])->assertNotFound();
        $this->actingAs($empresa)->post("/mis-pedidos/{$pedidoDeAna->id_pedido}/repetir")->assertNotFound();
        $this->actingAs($beto)->get('/mis-pedidos')->assertOk()->assertDontSee("data-pedido=\"{$pedidoDeAna->id_pedido}\"", false);

        $this->assertSame(EstadoPedido::PENDIENTE, $this->estado($pedidoDeAna));
        $this->actingAs($ana)->get("/mis-pedidos/{$pedidoDeAna->id_pedido}")->assertOk();
    }

    public function test_b2b_ve_los_pedidos_de_todos_los_puntos_de_entrega_de_su_empresa(): void
    {
        $empresa = $this->compradorCon('B2B');
        $casino = $this->direccionDe($empresa->cliente);
        $casino->update(['alias' => 'Casino de prueba']);

        $enBodega = $this->pedidoB2B($empresa);
        $enCasino = $this->pedidoB2B($empresa, $casino);
        $ajeno = $this->pedidoB2C($otro);

        $this->actingAs($empresa)->get('/mis-pedidos')->assertOk()
            ->assertSee("data-pedido=\"{$enBodega->id_pedido}\"", false)
            ->assertSee("data-pedido=\"{$enCasino->id_pedido}\"", false)
            ->assertSee('Casino de prueba')
            ->assertDontSee("data-pedido=\"{$ajeno->id_pedido}\"", false);
    }

    public function test_el_personal_no_entra_al_seguimiento_del_portal(): void
    {
        $this->get('/mis-pedidos')->assertRedirect('/login');

        $pedido = $this->pedidoB2C($cliente);
        $this->actingAs($this->despachador)->get('/mis-pedidos')->assertForbidden();
        $this->actingAs($this->despachador)->get("/mis-pedidos/{$pedido->id_pedido}")->assertForbidden();
    }

    // --- Detalle: línea de tiempo y despacho -----------------------------------

    public function test_rn09_el_detalle_muestra_la_linea_de_tiempo_del_historial(): void
    {
        $pedido = $this->pedidoConfirmado($cliente);

        $this->actingAs($cliente)->get("/mis-pedidos/{$pedido->id_pedido}")->assertOk()
            ->assertSee('data-paso="Pendiente"', false)
            ->assertSee('data-paso="Confirmado"', false)
            ->assertSee('data-sin-despacho', false)
            ->assertSeeInOrder(['data-paso="Pendiente"', 'data-paso="Confirmado"'], false);
    }

    public function test_el_detalle_muestra_camion_parada_y_rupturas_sin_datos_del_conductor(): void
    {
        $pedido = $this->pedidoConfirmado($cliente);
        $this->despachoEnRuta([$pedido]);
        $this->alerta('CAM-901', -8.4);

        $respuesta = $this->actingAs($cliente)->get("/mis-pedidos/{$pedido->id_pedido}")->assertOk()
            ->assertSee('data-paso="En preparación"', false)
            ->assertSee('data-paso="En ruta"', false)
            ->assertSee('CAM-901 (ZZ9901)')
            ->assertSee('Parada')
            ->assertSee('data-frio="ruptura"', false)
            ->assertSee('-8,4 °C');

        // SECURITY.md §6: el cliente ve camión, parada y frío, nunca al conductor.
        $html = $respuesta->getContent();
        $this->assertStringNotContainsString(self::CONDUCTOR_NOMBRE, $html);
        $this->assertStringNotContainsString(self::CONDUCTOR_RUT, $html);
        $this->assertStringNotContainsString('45999991', $html);
        $this->assertStringNotContainsString(self::CONDUCTOR_TELEFONO, $html);
        $this->assertStringNotContainsString('0199', $html);
    }

    public function test_un_trayecto_sin_alertas_se_informa_sin_rupturas(): void
    {
        $pedido = $this->pedidoConfirmado($cliente);
        $this->despachoEnRuta([$pedido]);
        // Una alerta de otro camión no toca a este pedido.
        Camion::create(['camion_id' => 'CAM-902', 'patente' => 'ZZ9902', 'capacidad_kg' => 3000]);
        $this->alerta('CAM-902', -7.0);

        $this->actingAs($cliente)->get("/mis-pedidos/{$pedido->id_pedido}")->assertOk()
            ->assertSee('data-frio="ok"', false)
            ->assertSee('Sin rupturas de frío registradas en el trayecto.')
            ->assertDontSee('data-frio="ruptura"', false);
    }

    // --- Cancelar (RN-04) ------------------------------------------------------

    public function test_rn04_el_cliente_cancela_un_pedido_pendiente_y_se_libera_la_reserva(): void
    {
        $pedido = $this->pedidoB2C($cliente);
        $this->assertSame('3.00', $this->reservado());

        $this->actingAs($cliente)->get("/mis-pedidos/{$pedido->id_pedido}")->assertSee('data-cancelar', false);
        $this->actingAs($cliente)->post("/mis-pedidos/{$pedido->id_pedido}/cancelar", ['motivo' => 'Me equivoqué de fecha'])
            ->assertRedirect("/mis-pedidos/{$pedido->id_pedido}");

        $this->assertSame(EstadoPedido::CANCELADO, $this->estado($pedido));
        $this->assertSame('0.00', $this->reservado());
        $this->assertSame('Cancelado por el cliente: Me equivoqué de fecha', $pedido->historialesEstadoPedido()->orderByDesc('id_historial')->value('comentario'));
    }

    public function test_rn04_el_cliente_cancela_un_pedido_confirmado_antes_de_la_preparacion(): void
    {
        $pedido = $this->pedidoConfirmado($cliente);

        $this->actingAs($cliente)->post("/mis-pedidos/{$pedido->id_pedido}/cancelar", ['motivo' => 'Ya no lo necesito'])->assertRedirect();

        $this->assertSame(EstadoPedido::CANCELADO, $this->estado($pedido));
        $this->assertSame('0.00', $this->reservado());
    }

    public function test_cancelar_exige_motivo(): void
    {
        $pedido = $this->pedidoB2C($cliente);

        $this->actingAs($cliente)->post("/mis-pedidos/{$pedido->id_pedido}/cancelar", ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->assertSame(EstadoPedido::PENDIENTE, $this->estado($pedido));
    }

    public function test_no_se_puede_cancelar_un_pedido_en_preparacion_o_posterior(): void
    {
        $pedido = $this->pedidoConfirmado($cliente);
        $despacho = $this->armarDespacho([$pedido]);
        $this->assertSame(EstadoPedido::EN_PREPARACION, $this->estado($pedido));

        $this->actingAs($cliente)->get("/mis-pedidos/{$pedido->id_pedido}")->assertOk()->assertDontSee('data-cancelar', false);
        $this->actingAs($cliente)->post("/mis-pedidos/{$pedido->id_pedido}/cancelar", ['motivo' => 'Tarde'])->assertStatus(409);
        $this->assertSame(EstadoPedido::EN_PREPARACION, $this->estado($pedido));

        $this->actingAs($this->despachador)->post("/crm/despachos/{$despacho->id_despacho}/salida")->assertRedirect();
        $this->assertSame(EstadoPedido::EN_RUTA, $this->estado($pedido));

        $this->actingAs($cliente)->post("/mis-pedidos/{$pedido->id_pedido}/cancelar", ['motivo' => 'Tarde'])->assertStatus(409);
        $this->assertSame(EstadoPedido::EN_RUTA, $this->estado($pedido));
        $this->assertSame('3.00', $this->reservado());
    }

    // --- Repetir pedido (RN-01) ------------------------------------------------

    public function test_rn01_repetir_un_pedido_usa_el_precio_actual_y_no_el_pactado(): void
    {
        $empresa = $this->compradorCon('B2B');
        $pedido = $this->pedidoB2B($empresa);
        $this->assertSame('80000.00', (string) $pedido->detallesPedido()->value('precio_unitario_pactado'));

        // La lista institucional sube después del pedido.
        DetalleListaPrecio::where('id_lista_precio', $this->convenio->id_lista_precio)
            ->where('id_producto', $this->producto->id_producto)->update(['precio_unitario' => 95000]);

        $this->actingAs($empresa)->post("/mis-pedidos/{$pedido->id_pedido}/repetir")
            ->assertRedirect('/carrito')
            ->assertSessionHas('estado');

        $item = $this->itemsDelCarritoActivo()->sole();
        $this->assertSame([$this->producto->id_producto, '2.00'], [$item->id_producto, (string) $item->cantidad]);

        $this->actingAs($empresa)->get('/carrito')->assertOk()
            ->assertSee('$95.000')
            ->assertSee('$190.000')
            ->assertDontSee('$80.000');

        // El pedido original conserva su precio pactado.
        $this->assertSame('80000.00', (string) $pedido->detallesPedido()->value('precio_unitario_pactado'));
    }

    public function test_repetir_omite_los_productos_que_ya_no_se_venden(): void
    {
        $empresa = $this->compradorCon('B2B');
        $pedido = $this->pedidoB2B($empresa);
        $this->producto->update(['activo' => false]);

        $this->actingAs($empresa)->post("/mis-pedidos/{$pedido->id_pedido}/repetir")
            ->assertRedirect('/carrito')
            ->assertSessionHas('aviso', 'El producto no existe o ya no se vende.');

        $this->assertSame(0, $this->itemsDelCarritoActivo()->count());
    }

    public function test_repetir_pedido_es_solo_para_empresas(): void
    {
        $pedido = $this->pedidoB2C($cliente);

        $this->actingAs($cliente)->post("/mis-pedidos/{$pedido->id_pedido}/repetir")->assertForbidden();
        $this->actingAs($cliente)->get("/mis-pedidos/{$pedido->id_pedido}")->assertDontSee('data-repetir', false);
    }

    // --- Puntos de entrega (RN-06) ---------------------------------------------

    public function test_rn06_b2b_agrega_puntos_de_entrega_con_ventana_y_se_ofrecen_en_el_checkout(): void
    {
        $empresa = $this->compradorCon('B2B');
        $comuna = DireccionDespacho::where('id_cliente', $empresa->cliente->id_cliente)->value('id_comuna');

        $this->actingAs($empresa)->post('/mis-direcciones', [
            'alias' => 'Casino norte', 'calle' => 'Avenida Ficticia 456', 'id_comuna' => $comuna,
            'ventana_inicio' => '07:30', 'ventana_fin' => '10:00',
        ])->assertRedirect('/mis-direcciones');

        $nueva = DireccionDespacho::where('alias', 'Casino norte')->sole();
        $this->assertSame([$empresa->cliente->id_cliente, true, '07:30:00', '10:00:00'], [$nueva->id_cliente, $nueva->activa, $nueva->ventana_inicio, $nueva->ventana_fin]);

        $this->actingAs($empresa)->post('/mis-direcciones', [
            'alias' => 'Mal', 'calle' => 'Calle 1', 'id_comuna' => $comuna, 'ventana_inicio' => '12:00', 'ventana_fin' => '09:00',
        ])->assertSessionHasErrors('ventana_fin');

        $this->alCarrito($empresa, 2);
        $this->actingAs($empresa)->get('/checkout')->assertOk()->assertSee('Casino norte');
    }

    public function test_b2b_desactiva_un_punto_pero_siempre_conserva_uno_activo(): void
    {
        $empresa = $this->compradorCon('B2B');
        $bodega = DireccionDespacho::where('id_cliente', $empresa->cliente->id_cliente)->sole();
        $casino = $this->direccionDe($empresa->cliente);

        $this->actingAs($empresa)->patch("/mis-direcciones/{$casino->id_direccion}", ['activa' => '0'])->assertRedirect('/mis-direcciones');
        $this->assertFalse($casino->refresh()->activa);

        $this->actingAs($empresa)->patch("/mis-direcciones/{$bodega->id_direccion}", ['activa' => '0'])
            ->assertSessionHasErrors(['direccion' => 'Debes mantener al menos un punto de entrega activo.']);
        $this->assertTrue($bodega->refresh()->activa);

        $this->actingAs($empresa)->patch("/mis-direcciones/{$casino->id_direccion}", ['activa' => '1'])->assertRedirect();
        $this->assertTrue($casino->refresh()->activa);
    }

    public function test_los_puntos_de_entrega_ajenos_responden_404_y_b2c_no_agrega_puntos(): void
    {
        $empresa = $this->compradorCon('B2B');
        $otra = $this->compradorCon('B2B');
        $persona = $this->compradorCon('B2C');
        $ajena = DireccionDespacho::where('id_cliente', $otra->cliente->id_cliente)->sole();

        $this->actingAs($empresa)->patch("/mis-direcciones/{$ajena->id_direccion}", ['activa' => '0'])->assertNotFound();
        $this->assertTrue($ajena->refresh()->activa);
        $this->actingAs($empresa)->get('/mis-direcciones')->assertOk()->assertDontSee("data-direccion=\"{$ajena->id_direccion}\"", false);

        $this->actingAs($persona)->get('/mis-direcciones')->assertOk()->assertDontSee('data-nueva-direccion', false);
        $this->actingAs($persona)->post('/mis-direcciones', ['alias' => 'X', 'calle' => 'Y', 'id_comuna' => $ajena->id_comuna])->assertForbidden();
    }

    // --- Apoyo -----------------------------------------------------------------

    private function alCarrito(Usuario $cuenta, int $cantidad): void
    {
        $this->actingAs($cuenta)->post('/carrito/items', ['sku' => 'TST-SEG', 'cantidad' => $cantidad])->assertSessionHasNoErrors();
    }

    /** Pedido B2C «Pendiente» de 3 × $12.000. */
    private function pedidoB2C(?Usuario &$cliente): Pedido
    {
        $cliente = $this->compradorCon('B2C');
        $this->alCarrito($cliente, 3);
        $this->actingAs($cliente)->post('/pedidos', $this->datosCheckout($cliente))->assertSessionHasNoErrors();

        return Pedido::latest('id_pedido')->firstOrFail();
    }

    private function pedidoConfirmado(?Usuario &$cliente): Pedido
    {
        $pedido = $this->pedidoB2C($cliente);
        $this->actingAs($cliente)->post("/pedidos/{$pedido->id_pedido}/pago", ['metodo' => 'tarjeta', 'resultado' => 'aprobado'])->assertRedirect();

        return $pedido;
    }

    /** Pedido B2B «Pendiente» de 2 × $80.000 neto. */
    private function pedidoB2B(Usuario $empresa, ?DireccionDespacho $direccion = null): Pedido
    {
        $this->alCarrito($empresa, 2);
        $datos = $this->datosCheckout($empresa, $direccion ? ['id_direccion' => $direccion->id_direccion] : []);
        $this->actingAs($empresa)->post('/pedidos', $datos)->assertSessionHasNoErrors();

        return Pedido::latest('id_pedido')->firstOrFail();
    }

    /** @param  list<Pedido>  $pedidos */
    private function armarDespacho(array $pedidos): Despacho
    {
        $this->actingAs($this->despachador)->post('/crm/despachos', [
            'camion_id' => 'CAM-901',
            'id_conductor' => Conductor::value('id_conductor'),
            'fecha_programada' => now(config('app.timezone'))->toDateString(),
            'pedidos' => array_map(fn (Pedido $p) => $p->id_pedido, $pedidos),
        ])->assertSessionHasNoErrors();

        return Despacho::latest('id_despacho')->firstOrFail();
    }

    /** @param  list<Pedido>  $pedidos */
    private function despachoEnRuta(array $pedidos): Despacho
    {
        $despacho = $this->armarDespacho($pedidos);
        $this->actingAs($this->despachador)->post("/crm/despachos/{$despacho->id_despacho}/salida")->assertRedirect();

        return $despacho->refresh();
    }

    private function alerta(string $camion, float $temperatura): Alerta
    {
        $lectura = Telemetria::create(['camion_id' => $camion, 'fecha_hora' => now()->addSecond(), 'temp_camara' => $temperatura, 'lat' => -38.73, 'long' => -72.59, 'combustible_pct' => 60, 'alerta_ruptura_frio' => true]);

        return Alerta::create(['id_telemetria' => $lectura->id_telemetria, 'tipo' => 'ruptura_frio', 'severidad' => 'alta', 'estado' => 'abierta']);
    }

    /** Ítems del carrito `activo` (el del pedido original quedó `convertido`). */
    private function itemsDelCarritoActivo(): Builder
    {
        return ItemCarrito::whereIn('id_carrito', Carrito::where('estado', 'activo')->select('id_carrito'));
    }

    private function estado(Pedido $pedido): string
    {
        return $pedido->refresh()->estadoPedido->nombre;
    }

    private function reservado(): string
    {
        return (string) Inventario::find($this->producto->id_producto)->stock_reservado;
    }
}
