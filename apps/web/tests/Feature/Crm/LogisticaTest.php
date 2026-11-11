<?php

namespace Tests\Feature\Crm;

use App\Domain\Clientes\Rut;
use App\Domain\Logistica\NotificarAlertasNuevas;
use App\Models\Alerta;
use App\Models\Camion;
use App\Models\Conductor;
use App\Models\Despacho;
use App\Models\DespachoPedido;
use App\Models\EstadoPedido;
use App\Models\HistorialEstadoPedido;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\RangoConservacion;
use App\Models\Rol;
use App\Models\Telemetria;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Notifications\AlertaFrioNueva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreaUsuarios;
use Tests\Support\CreaVentas;
use Tests\TestCase;

/** Despachos, flota y alertas de frío (T-017). Datos ficticios. */
class LogisticaTest extends TestCase
{
    use CreaUsuarios;
    use CreaVentas;
    use RefreshDatabase;

    private Usuario $despachador;

    private Usuario $ana;

    private Usuario $beto;

    private int $rut = 45_300_000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVentas();
        $this->despachador = $this->usuario(Rol::DESPACHADOR);
        $this->ana = $this->vendedor('Ana Vendedora');
        $this->beto = $this->vendedor('Beto Vendedor');

        $this->productoDeRango('CONG-1', 'Congelado', 'Helado de prueba');
        $this->productoDeRango('REF-1', 'Refrigerado', 'Yogur de prueba');

        foreach (['CAM-801', 'CAM-802'] as $i => $camion) {
            Camion::create(['camion_id' => $camion, 'patente' => 'ZZ880'.$i, 'capacidad_kg' => 3000]);
            Conductor::create(['rut' => Rut::desdeNumero(++$this->rut)->normalizado(), 'nombre' => "Conductor de prueba {$i}"]);
        }
    }

    // --- Armado (RN-07) --------------------------------------------------------

    public function test_rn07_no_se_puede_crear_un_despacho_que_mezcle_refrigerados_y_congelados(): void
    {
        $congelado = $this->pedidoConfirmado('CONG-1');
        $refrigerado = $this->pedidoConfirmado('REF-1');

        $this->actingAs($this->despachador)->post('/crm/despachos', $this->datosDespacho('CAM-801', [$congelado, $refrigerado]))
            ->assertSessionHasErrors(['pedidos' => 'RN-07: un despacho no puede mezclar rangos de conservación (Congelado, Refrigerado). El camión tiene una sola cámara.']);

        $this->assertSame(0, Despacho::count());
        $this->assertSame([EstadoPedido::CONFIRMADO, EstadoPedido::CONFIRMADO], [$this->estado($congelado), $this->estado($refrigerado)]);
    }

    public function test_armar_un_despacho_ordena_las_paradas_y_deja_los_pedidos_en_preparacion(): void
    {
        $primero = $this->pedidoConfirmado('CONG-1');
        $segundo = $this->pedidoConfirmado('CONG-1');

        // El segundo pedido va como parada 1.
        $this->actingAs($this->despachador)->post('/crm/despachos', $this->datosDespacho('CAM-801', [$primero, $segundo], [$segundo->id_pedido => 1, $primero->id_pedido => 2]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $despacho = Despacho::sole();
        $this->assertSame(['programado', 'CAM-801'], [$despacho->estado, $despacho->camion_id]);
        $this->assertSame([$segundo->id_pedido, $primero->id_pedido], DespachoPedido::orderBy('orden_parada')->pluck('id_pedido')->all());
        $this->assertSame([EstadoPedido::EN_PREPARACION, EstadoPedido::EN_PREPARACION], [$this->estado($primero), $this->estado($segundo)]);
        $this->assertSame("Asignado al despacho {$despacho->id_despacho}", $this->ultimoHistorial($primero)->comentario);

        // El camión ya está ocupado y un pedido pendiente no se despacha.
        $pendiente = $this->pedidoPendiente('CONG-1');
        $otro = $this->pedidoConfirmado('CONG-1');
        $this->actingAs($this->despachador)->post('/crm/despachos', $this->datosDespacho('CAM-801', [$otro], conductor: 1))->assertSessionHasErrors('camion_id');
        $this->actingAs($this->despachador)->post('/crm/despachos', $this->datosDespacho('CAM-802', [$pendiente], conductor: 1))->assertSessionHasErrors('pedidos');
    }

    // --- Salida y resultados por parada ------------------------------------------

    public function test_la_salida_deja_el_despacho_y_sus_pedidos_en_ruta(): void
    {
        $despacho = $this->despachoEnRuta([$pedido = $this->pedidoConfirmado('CONG-1')]);

        $this->assertSame('en_ruta', $despacho->estado);
        $this->assertNotNull($despacho->salida_real);
        $this->assertSame(EstadoPedido::EN_RUTA, $this->estado($pedido));
        $this->assertSame('Salida del camión CAM-801', $this->ultimoHistorial($pedido)->comentario);

        $this->actingAs($this->despachador)->post("/crm/despachos/{$despacho->id_despacho}/salida")->assertStatus(409);
    }

    public function test_la_entrega_descuenta_disponible_y_reservado_y_registra_la_salida(): void
    {
        $pedido = $this->pedidoConfirmado('CONG-1', cantidad: 3);
        $producto = Producto::where('sku', 'CONG-1')->sole();
        $this->assertSame(['100.00', '3.00'], $this->stock($producto));
        $despacho = $this->despachoEnRuta([$pedido]);

        $this->actingAs($this->despachador)->post("/crm/despachos/{$despacho->id_despacho}/paradas/{$pedido->id_pedido}", ['resultado' => 'entregado'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['97.00', '0.00'], $this->stock($producto));
        $this->assertSame('-3.00', (string) MovimientoInventario::where('id_pedido', $pedido->id_pedido)->where('tipo', 'salida')->sole()->cantidad);
        $this->assertSame(EstadoPedido::ENTREGADO, $this->estado($pedido));

        $parada = DespachoPedido::sole();
        $this->assertSame('entregado', $parada->estado_entrega);
        $this->assertNotNull($parada->fecha_entrega);

        // Sin paradas pendientes, el despacho se cierra.
        $despacho->refresh();
        $this->assertSame('finalizado', $despacho->estado);
        $this->assertNotNull($despacho->llegada_real);
    }

    public function test_un_rechazo_exige_motivo_da_de_baja_la_carga_y_abre_un_ticket(): void
    {
        $pedido = $this->pedidoConfirmado('CONG-1', vendedor: $this->ana);
        $despacho = $this->despachoEnRuta([$pedido]);
        $url = "/crm/despachos/{$despacho->id_despacho}/paradas/{$pedido->id_pedido}";

        $this->actingAs($this->despachador)->post($url, ['resultado' => 'rechazado'])->assertSessionHasErrors('motivo');
        $this->actingAs($this->despachador)->post($url, ['resultado' => 'rechazado', 'motivo' => 'Carga recibida a -9 °C'])->assertSessionHasNoErrors();

        $this->assertSame(EstadoPedido::RECHAZADO, $this->estado($pedido));
        $this->assertSame(['rechazado_temperatura', 'Carga recibida a -9 °C'], [DespachoPedido::sole()->estado_entrega, DespachoPedido::sole()->motivo_falla]);
        $this->assertSame(1, MovimientoInventario::where('id_pedido', $pedido->id_pedido)->where('tipo', 'merma')->count());

        $ticket = Ticket::sole();
        $this->assertSame([$pedido->id_pedido, 'reclamo', 'alta', 'abierto', $this->ana->vendedor->id_vendedor], [$ticket->id_pedido, $ticket->categoria, $ticket->prioridad, $ticket->estado, $ticket->id_vendedor]);
    }

    public function test_una_entrega_fallida_devuelve_el_pedido_a_confirmado_y_permite_asignarlo_a_otro_despacho(): void
    {
        $fallido = $this->pedidoConfirmado('CONG-1');
        $entregado = $this->pedidoConfirmado('CONG-1');
        $primero = $this->despachoEnRuta([$fallido, $entregado]);

        $this->actingAs($this->despachador)->post("/crm/despachos/{$primero->id_despacho}/paradas/{$fallido->id_pedido}", ['resultado' => 'fallido'])
            ->assertSessionHasErrors('motivo');
        $this->actingAs($this->despachador)->post("/crm/despachos/{$primero->id_despacho}/paradas/{$fallido->id_pedido}", ['resultado' => 'fallido', 'motivo' => 'Local cerrado'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EstadoPedido::CONFIRMADO, $this->estado($fallido));
        $this->assertSame('Entrega fallida: Local cerrado', $this->ultimoHistorial($fallido)->comentario);
        $this->assertSame('en_ruta', $primero->refresh()->estado); // queda otra parada pendiente
        // La reserva se mantiene: el pedido sigue vigente.
        $this->assertSame('4.00', (string) Inventario::find(Producto::where('sku', 'CONG-1')->value('id_producto'))->stock_reservado);

        // Ya no tiene parada pendiente: entra en otro despacho con otro camión.
        $this->actingAs($this->despachador)->post('/crm/despachos', $this->datosDespacho('CAM-802', [$fallido], conductor: 1))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, DespachoPedido::where('id_pedido', $fallido->id_pedido)->count());
        $this->assertSame(EstadoPedido::EN_PREPARACION, $this->estado($fallido));
    }

    // --- Alertas ---------------------------------------------------------------

    public function test_resolver_una_alerta_sin_accion_correctiva_se_rechaza(): void
    {
        $alerta = $this->alerta('CAM-801');

        $this->actingAs($this->despachador)->post("/crm/alertas/{$alerta->id_alerta}/resolver", ['accion_correctiva' => ''])->assertSessionHasErrors('accion_correctiva');
        $this->actingAs($this->despachador)->post("/crm/alertas/{$alerta->id_alerta}/resolver", ['accion_correctiva' => 'ok'])->assertSessionHasErrors('accion_correctiva');
        $this->assertSame('abierta', $alerta->refresh()->estado);

        // El vendedor no resuelve alertas.
        $this->actingAs($this->ana)->post("/crm/alertas/{$alerta->id_alerta}/resolver", ['accion_correctiva' => 'Se reinició el equipo de frío'])->assertForbidden();

        $this->actingAs($this->despachador)->post("/crm/alertas/{$alerta->id_alerta}/resolver", ['accion_correctiva' => 'Se reinició el equipo de frío en ruta'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $alerta->refresh();
        $this->assertSame(['resuelta', 'Se reinició el equipo de frío en ruta', $this->despachador->id_usuario], [$alerta->estado, $alerta->accion_correctiva, $alerta->id_usuario_resuelve]);
        $this->assertNotNull($alerta->fecha_resolucion);

        $this->actingAs($this->despachador)->post("/crm/alertas/{$alerta->id_alerta}/resolver", ['accion_correctiva' => 'Otra acción correctiva'])->assertStatus(409);
    }

    public function test_rn10_el_vendedor_solo_ve_alertas_de_su_cartera(): void
    {
        $deAna = $this->pedidoConfirmado('CONG-1', vendedor: $this->ana, nombre: 'Cliente de Ana');
        $deBeto = $this->pedidoConfirmado('CONG-1', vendedor: $this->beto, nombre: 'Cliente de Beto');
        $this->despachoEnRuta([$deAna, $deBeto]);
        $enRuta = $this->alerta('CAM-801');
        $sinCarga = $this->alerta('CAM-802');

        $this->actingAs($this->ana)->get('/crm/alertas')->assertOk()
            ->assertSee("data-alerta=\"{$enRuta->id_alerta}\"", false)
            ->assertSee('Cliente de Ana')
            ->assertDontSee('Cliente de Beto')
            ->assertDontSee("data-alerta=\"{$sinCarga->id_alerta}\"", false)
            ->assertDontSee('Resolver');

        $this->actingAs($this->vendedor('Carla Vendedora'))->get('/crm/alertas')->assertOk()
            ->assertDontSee("data-alerta=\"{$enRuta->id_alerta}\"", false)
            ->assertSee('data-sin-alertas', false);

        $this->actingAs($this->despachador)->get('/crm/alertas')->assertOk()
            ->assertSee("data-alerta=\"{$enRuta->id_alerta}\"", false)
            ->assertSee("data-alerta=\"{$sinCarga->id_alerta}\"", false)
            ->assertSee('Cliente de Ana')
            ->assertSee('Cliente de Beto')
            ->assertSee('Resolver');
    }

    public function test_una_alerta_nueva_se_avisa_al_despachador_y_a_los_vendedores_afectados(): void
    {
        Notification::fake();
        $this->alerta('CAM-802'); // historial previo: no se avisa de golpe

        $this->artisan('alertas:notificar')->assertSuccessful();
        Notification::assertNothingSent();

        $deAna = $this->pedidoConfirmado('CONG-1', vendedor: $this->ana);
        $this->despachoEnRuta([$deAna]);
        $nueva = $this->alerta('CAM-801');

        $this->artisan('alertas:notificar')->expectsOutputToContain('1')->assertSuccessful();

        Notification::assertSentOnDemand(AlertaFrioNueva::class, function (AlertaFrioNueva $aviso, array $canales, AnonymousNotifiable $destino) use ($nueva) {
            $correos = $destino->routes['mail'];
            sort($correos);
            $esperados = [$this->ana->email, $this->despachador->email];
            sort($esperados);

            return $aviso->alerta->is($nueva) && $correos === $esperados && $aviso->afectados->count() === 1;
        });
        Notification::assertSentOnDemandTimes(AlertaFrioNueva::class, 1);

        // La siguiente pasada no repite.
        $this->artisan('alertas:notificar')->assertSuccessful();
        Notification::assertSentOnDemandTimes(AlertaFrioNueva::class, 1);
        $this->assertSame($nueva->id_alerta, cache(NotificarAlertasNuevas::CLAVE));
    }

    public function test_los_trabajos_de_logistica_estan_en_el_planificador(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('alertas:notificar')->assertSuccessful();
    }

    // --- Flota y acceso ----------------------------------------------------------

    public function test_la_flota_muestra_la_ultima_lectura_de_cada_camion(): void
    {
        Telemetria::create(['camion_id' => 'CAM-801', 'fecha_hora' => now()->subMinutes(5), 'temp_camara' => -20.5, 'lat' => -38.7, 'long' => -72.6, 'combustible_pct' => 80, 'alerta_ruptura_frio' => false]);
        Telemetria::create(['camion_id' => 'CAM-801', 'fecha_hora' => now(), 'temp_camara' => -19.2, 'lat' => -38.7, 'long' => -72.6, 'combustible_pct' => 79, 'alerta_ruptura_frio' => false]);
        $this->alerta('CAM-802');

        $this->actingAs($this->despachador)->get('/crm/flota')->assertOk()
            ->assertSee('-19,2 °C')
            ->assertDontSee('-20,5 °C')
            ->assertSee('79 %')
            ->assertSee('data-alerta-abierta', false);
    }

    public function test_el_vendedor_no_entra_a_despachos_ni_flota(): void
    {
        $this->actingAs($this->ana)->get('/crm/despachos')->assertForbidden();
        $this->actingAs($this->ana)->get('/crm/flota')->assertForbidden();
        $this->actingAs($this->ana)->get('/crm/alertas')->assertOk();
        $this->actingAs($this->despachador)->get('/crm/despachos')->assertOk();
    }

    // --- Apoyo ---------------------------------------------------------------

    private function productoDeRango(string $sku, string $rango, string $nombre): void
    {
        $producto = $this->productoVenta($sku, publico: 10000, convenio: 80000, stock: 100, nombre: $nombre);
        $producto->update(['id_rango' => RangoConservacion::where('nombre', $rango)->value('id_rango')]);
    }

    private function pedidoPendiente(string $sku, ?Usuario $vendedor = null, int $cantidad = 2, ?string $nombre = null): Pedido
    {
        $cuenta = $this->compradorCon('B2C', $vendedor);
        if ($nombre !== null) {
            $cuenta->cliente->update(['nombre_razon_social' => $nombre]);
        }

        $this->actingAs($cuenta)->post('/carrito/items', ['sku' => $sku, 'cantidad' => $cantidad])->assertSessionHasNoErrors();
        $this->actingAs($cuenta)->post('/pedidos', $this->datosCheckout($cuenta))->assertSessionHasNoErrors();

        return Pedido::latest('id_pedido')->firstOrFail();
    }

    private function pedidoConfirmado(string $sku, ?Usuario $vendedor = null, int $cantidad = 2, ?string $nombre = null): Pedido
    {
        $pedido = $this->pedidoPendiente($sku, $vendedor, $cantidad, $nombre);
        $this->actingAs($pedido->direccionDespacho->cliente->usuario)
            ->post("/pedidos/{$pedido->id_pedido}/pago", ['metodo' => 'tarjeta', 'resultado' => 'aprobado'])
            ->assertRedirect('/');

        return $pedido;
    }

    /** @param  list<Pedido>  $pedidos */
    private function despachoEnRuta(array $pedidos): Despacho
    {
        $this->actingAs($this->despachador)->post('/crm/despachos', $this->datosDespacho('CAM-801', $pedidos))->assertSessionHasNoErrors();
        $despacho = Despacho::latest('id_despacho')->firstOrFail();
        $this->actingAs($this->despachador)->post("/crm/despachos/{$despacho->id_despacho}/salida")->assertRedirect();

        return $despacho->refresh();
    }

    /**
     * @param  list<Pedido>  $pedidos
     * @param  array<int, int>  $orden
     */
    private function datosDespacho(string $camion, array $pedidos, array $orden = [], int $conductor = 0): array
    {
        return [
            'camion_id' => $camion,
            'id_conductor' => Conductor::orderBy('id_conductor')->skip($conductor)->value('id_conductor'),
            'fecha_programada' => now(config('app.timezone'))->toDateString(),
            'pedidos' => array_map(fn (Pedido $p) => $p->id_pedido, $pedidos),
            'orden' => $orden,
        ];
    }

    private function alerta(string $camion): Alerta
    {
        $lectura = Telemetria::create(['camion_id' => $camion, 'fecha_hora' => now(), 'temp_camara' => -9.0, 'lat' => -38.73, 'long' => -72.59, 'combustible_pct' => 60, 'alerta_ruptura_frio' => true]);

        return Alerta::create(['id_telemetria' => $lectura->id_telemetria, 'tipo' => 'ruptura_frio', 'severidad' => 'alta', 'estado' => 'abierta']);
    }

    private function estado(Pedido $pedido): string
    {
        return $pedido->refresh()->estadoPedido->nombre;
    }

    private function ultimoHistorial(Pedido $pedido): HistorialEstadoPedido
    {
        return HistorialEstadoPedido::where('id_pedido', $pedido->id_pedido)->orderByDesc('id_historial')->firstOrFail();
    }

    /** @return array{0: string, 1: string} */
    private function stock(Producto $producto): array
    {
        $inventario = Inventario::find($producto->id_producto);

        return [(string) $inventario->stock_disponible, (string) $inventario->stock_reservado];
    }
}
