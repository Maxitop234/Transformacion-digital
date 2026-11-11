<?php

namespace Tests\Feature\Crm;

use App\Domain\Clientes\Rut;
use App\Domain\Crm\PanelCrm;
use App\Models\Alerta;
use App\Models\Camion;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Despacho;
use App\Models\DespachoPedido;
use App\Models\DetallePedido;
use App\Models\DireccionDespacho;
use App\Models\EstadoPedido;
use App\Models\EtapaPipeline;
use App\Models\Interaccion;
use App\Models\Oportunidad;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Telemetria;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreaUsuarios;
use Tests\Support\CreaVentas;
use Tests\TestCase;

/** Panel de inicio del CRM (T-013). Datos ficticios. */
class PanelCrmTest extends TestCase
{
    use CreaUsuarios;
    use CreaVentas;
    use RefreshDatabase;

    private Usuario $ana;

    private Usuario $beto;

    private int $secuenciaRut = 45_100_000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVentas();
        $this->productoVenta('TST-001', publico: 1000, convenio: 1000);

        $this->ana = $this->vendedor('Ana Vendedora');
        $this->beto = $this->vendedor('Beto Vendedor');
    }

    // --- Alcance de la cartera (RN-10) ---------------------------------------

    public function test_rn10_el_vendedor_ve_indicadores_solo_de_su_cartera_y_el_administrador_de_todo_el_equipo(): void
    {
        $deAna = $this->clienteDe($this->ana, 'Empresa de Ana');
        $deBeto = $this->clienteDe($this->beto, 'Empresa de Beto');

        // Ana: 2 abiertas + 1 ganada (3 creadas); Beto: 1 abierta.
        $this->oportunidad($deAna, 'Prospecto nuevo');
        $this->oportunidad($deAna, 'Propuesta');
        $this->oportunidad($deAna, 'Ganado');
        $this->oportunidad($deBeto, 'Contactado');

        // Pedidos: Ana 2 portal + 1 crm; Beto 1 portal; un cancelado de Ana no cuenta.
        $this->pedido($deAna, 'portal', neto: 10000);
        $this->pedido($deAna, 'portal', neto: 5000);
        $this->pedido($deAna, 'crm', neto: 20000);
        $this->pedido($deAna, 'portal', neto: 99000, estado: EstadoPedido::CANCELADO);
        $this->pedido($deBeto, 'portal', neto: 7000);

        // Tickets: Ana 1 abierto + 1 en proceso + 1 cerrado; Beto 1 abierto.
        foreach (['abierto', 'en_proceso', 'cerrado'] as $estado) {
            $this->ticket($deAna, $estado);
        }
        $this->ticket($deBeto, 'abierto');

        $panel = app(PanelCrm::class);

        $deAnaIndicadores = $panel->indicadores($this->ana);
        $this->assertSame(2, $deAnaIndicadores['oportunidades_abiertas']);
        $this->assertSame(['creadas' => 3, 'ganadas' => 1, 'tasa' => 33.3], $deAnaIndicadores['conversion']);
        $this->assertSame(['pedidos' => 2, 'monto_neto' => 15000], $deAnaIndicadores['pedidos_por_canal']['portal']);
        $this->assertSame(['pedidos' => 1, 'monto_neto' => 20000], $deAnaIndicadores['pedidos_por_canal']['crm']);
        $this->assertSame(2, $deAnaIndicadores['tickets_abiertos']);

        $todos = $panel->indicadores($this->usuario(Rol::ADMINISTRADOR));
        $this->assertSame(3, $todos['oportunidades_abiertas']);
        $this->assertSame(['creadas' => 4, 'ganadas' => 1, 'tasa' => 25.0], $todos['conversion']);
        $this->assertSame(['pedidos' => 3, 'monto_neto' => 22000], $todos['pedidos_por_canal']['portal']);
        $this->assertSame(3, $todos['tickets_abiertos']);

        // La vista muestra lo mismo.
        $this->actingAs($this->ana)->get('/crm')->assertOk()
            ->assertSee('Indicadores de tu cartera.')
            ->assertSee('data-indicador="oportunidades-abiertas">2<', false)
            ->assertSee('33,3 %')
            ->assertSee('data-canal="portal" data-pedidos="2"', false);
        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->get('/crm')->assertOk()
            ->assertSee('Indicadores de todo el equipo.')
            ->assertSee('data-indicador="oportunidades-abiertas">3<', false)
            ->assertSee('25,0 %');
    }

    public function test_rn10_las_listas_del_panel_tambien_respetan_la_cartera(): void
    {
        $pendienteAna = $this->clienteDe($this->ana, 'Pendiente de Ana');
        $pendienteAna->update(['estado_cliente' => 'pendiente_validacion']);
        $pendienteBeto = $this->clienteDe($this->beto, 'Pendiente de Beto');
        $pendienteBeto->update(['estado_cliente' => 'pendiente_validacion']);
        $this->oportunidad($this->clienteDe($this->beto, 'Otra de Beto'), 'Contactado', titulo: 'Oportunidad de Beto', creada: now()->subDays(40));

        $this->actingAs($this->ana)->get('/crm')->assertOk()
            ->assertSee('Pendiente de Ana')
            ->assertDontSee('Pendiente de Beto')
            ->assertDontSee('Oportunidad de Beto');

        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->get('/crm')->assertOk()
            ->assertSee('Pendiente de Ana')
            ->assertSee('Pendiente de Beto')
            ->assertSee('Oportunidad de Beto');
    }

    // --- Roles ---------------------------------------------------------------

    public function test_el_despachador_no_ve_bloques_comerciales(): void
    {
        $cliente = $this->clienteDe($this->ana, 'Empresa comercial');
        $cliente->update(['estado_cliente' => 'pendiente_validacion']);
        $this->oportunidad($cliente, 'Contactado', titulo: 'Oportunidad reservada', creada: now()->subDays(40));
        $this->alertaEnRuta($this->clienteDe($this->ana, 'Cliente con alerta'));

        $respuesta = $this->actingAs($this->usuario(Rol::DESPACHADOR))->get('/crm')->assertOk();

        foreach (['indicadores', 'empresas-pendientes', 'oportunidades-sin-actividad'] as $bloque) {
            $respuesta->assertDontSee("data-bloque=\"{$bloque}\"", false);
        }
        $respuesta->assertDontSee('Empresa comercial')
            ->assertDontSee('Oportunidad reservada')
            ->assertSee('data-bloque="alertas"', false)
            ->assertSee('Cliente con alerta');
    }

    // --- Alertas de frío -----------------------------------------------------

    public function test_una_alerta_abierta_sobre_un_camion_en_ruta_aparece_con_los_clientes_afectados(): void
    {
        $deAna = $this->clienteDe($this->ana, 'Clínica de Ana');
        $deBeto = $this->clienteDe($this->beto, 'Casino de Beto');
        $alerta = $this->alertaEnRuta($deAna, $deBeto);

        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->get('/crm')->assertOk()
            ->assertSee("data-alerta=\"{$alerta->id_alerta}\"", false)
            ->assertSee('CAM-901')
            ->assertSee('Clínica de Ana')
            ->assertSee('Casino de Beto');

        // El vendedor ve la alerta, pero solo con los clientes de su cartera.
        $this->actingAs($this->ana)->get('/crm')->assertOk()
            ->assertSee("data-alerta=\"{$alerta->id_alerta}\"", false)
            ->assertSee('Clínica de Ana')
            ->assertDontSee('Casino de Beto');

        // A un vendedor sin clientes afectados no le aparece.
        $this->actingAs($this->vendedor('Carla Vendedora'))->get('/crm')->assertOk()
            ->assertDontSee("data-alerta=\"{$alerta->id_alerta}\"", false)
            ->assertSee('Ninguna alerta abierta afecta pedidos de tu cartera.')
            ->assertDontSee('data-alertas-total', false);
    }

    public function test_no_aparecen_alertas_resueltas_ni_de_camiones_sin_despacho_en_ruta(): void
    {
        $cliente = $this->clienteDe($this->ana, 'Cliente tranquilo');
        $resuelta = $this->alertaEnRuta($cliente);
        $resuelta->update(['estado' => 'resuelta', 'accion_correctiva' => 'Se ajustó el equipo', 'fecha_resolucion' => now()]);

        // Lectura de otro camión que no lleva carga.
        Camion::create(['camion_id' => 'CAM-902', 'patente' => 'ZZ9902', 'capacidad_kg' => 3000]);
        $lectura = Telemetria::create(['camion_id' => 'CAM-902', 'fecha_hora' => now(), 'temp_camara' => 9.5, 'lat' => -38.7, 'long' => -72.6, 'combustible_pct' => 60, 'alerta_ruptura_frio' => true]);
        Alerta::create(['id_telemetria' => $lectura->id_telemetria, 'tipo' => 'ruptura_frio', 'severidad' => 'alta', 'estado' => 'abierta']);

        $this->assertTrue(app(PanelCrm::class)->alertasAbiertas($this->usuario(Rol::ADMINISTRADOR))->isEmpty());

        // El total de la flota sí cuenta la alerta sin carga, y se lo muestra a quien opera la flota.
        $this->actingAs($this->usuario(Rol::DESPACHADOR))->get('/crm')->assertOk()
            ->assertSee('Ninguna alerta abierta afecta pedidos.')
            ->assertSee('data-alertas-total="1"', false);
    }

    // --- Umbrales configurables ----------------------------------------------

    public function test_los_umbrales_de_15_y_90_dias_se_leen_de_configuracion(): void
    {
        $cliente = $this->clienteDe($this->ana, 'Empresa umbral');
        $hace20 = $this->oportunidad($cliente, 'Contactado', titulo: 'Sin contacto hace 20 días', creada: now()->subDays(20));
        $this->oportunidad($cliente, 'Ganado', creada: now()->subDays(100));

        $panel = app(PanelCrm::class);

        // Por defecto: 15 días sin actividad y ventana de 90 días.
        $this->assertSame(15, config('negocio.oportunidad_sin_actividad_dias'));
        $this->assertSame(90, config('negocio.conversion_ventana_dias'));
        $this->assertSame([$hace20->id_oportunidad], $panel->oportunidadesSinActividad($this->ana)->pluck('id_oportunidad')->all());
        $this->assertSame(['creadas' => 1, 'ganadas' => 0, 'tasa' => 0.0], $panel->indicadores($this->ana)['conversion']);

        config(['negocio.oportunidad_sin_actividad_dias' => 30, 'negocio.conversion_ventana_dias' => 120]);

        $this->assertTrue($panel->oportunidadesSinActividad($this->ana)->isEmpty());
        $this->assertSame(['creadas' => 2, 'ganadas' => 1, 'tasa' => 50.0], $panel->indicadores($this->ana)['conversion']);
        $this->actingAs($this->ana)->get('/crm')->assertSee('Oportunidades sin interacciones en 30 días o más');
    }

    public function test_una_interaccion_reciente_saca_a_la_oportunidad_de_la_lista_sin_actividad(): void
    {
        $cliente = $this->clienteDe($this->ana, 'Empresa con seguimiento');
        $ligada = $this->oportunidad($cliente, 'Calificado', titulo: 'Con interacción ligada', creada: now()->subDays(40));
        $otroCliente = $this->clienteDe($this->ana, 'Empresa con llamada');
        $porCliente = $this->oportunidad($otroCliente, 'Propuesta', titulo: 'Con llamada al cliente', creada: now()->subDays(40));
        $vieja = $this->oportunidad($this->clienteDe($this->ana, 'Empresa olvidada'), 'Contactado', titulo: 'Olvidada', creada: now()->subDays(40));

        $this->interaccion($cliente, $ligada, now()->subDays(3));
        $this->interaccion($otroCliente, null, now()->subDays(5)); // sin oportunidad: cuenta para el cliente
        $this->interaccion($vieja->cliente, $vieja, now()->subDays(16));

        $sinActividad = app(PanelCrm::class)->oportunidadesSinActividad($this->ana);

        $this->assertSame([$vieja->id_oportunidad], $sinActividad->pluck('id_oportunidad')->all());
        $this->assertSame(16, $sinActividad->first()->dias_sin_actividad);
        $this->assertNotContains($porCliente->id_oportunidad, $sinActividad->pluck('id_oportunidad'));
    }

    // --- Apoyo ---------------------------------------------------------------

    private function oportunidad(Cliente $cliente, string $etapa, string $titulo = 'Oportunidad de prueba', mixed $creada = null): Oportunidad
    {
        $etapa = EtapaPipeline::where('nombre', $etapa)->firstOrFail();

        return Oportunidad::create([
            'id_cliente' => $cliente->id_cliente,
            'id_vendedor' => $cliente->id_vendedor,
            'id_etapa' => $etapa->id_etapa,
            'titulo' => $titulo,
            'origen' => 'vendedor',
            'fecha_creacion' => $creada ?? now()->subDays(2),
            'fecha_cierre' => $etapa->es_cierre ? now() : null,
        ]);
    }

    private function interaccion(Cliente $cliente, ?Oportunidad $oportunidad, mixed $cuando): void
    {
        Interaccion::create([
            'id_cliente' => $cliente->id_cliente,
            'id_vendedor' => $cliente->id_vendedor,
            'id_oportunidad' => $oportunidad?->id_oportunidad,
            'tipo' => 'llamada',
            'resumen' => 'Seguimiento de prueba',
            'fecha_hora' => $cuando,
        ]);
    }

    private function pedido(Cliente $cliente, string $canal, int $neto, string $estado = EstadoPedido::PENDIENTE): Pedido
    {
        $direccion = DireccionDespacho::firstWhere('id_cliente', $cliente->id_cliente) ?? $this->direccionDe($cliente);

        $pedido = Pedido::create([
            'id_direccion' => $direccion->id_direccion,
            'id_estado_pedido' => EstadoPedido::idDe($estado),
            'canal' => $canal,
            'fecha_pedido' => now()->subDay(),
        ]);
        DetallePedido::create([
            'id_pedido' => $pedido->id_pedido,
            'id_producto' => Producto::where('sku', 'TST-001')->value('id_producto'),
            'cantidad' => 1,
            'precio_unitario_pactado' => $neto,
        ]);

        return $pedido;
    }

    private function ticket(Cliente $cliente, string $estado): void
    {
        Ticket::create([
            'id_cliente' => $cliente->id_cliente,
            'asunto' => 'Consulta de prueba',
            'descripcion' => 'Texto de prueba',
            'estado' => $estado,
        ]);
    }

    /** Camión CAM-901 en ruta con una parada por cliente y una alerta abierta. */
    private function alertaEnRuta(Cliente ...$clientes): Alerta
    {
        if (! Camion::whereKey('CAM-901')->exists()) {
            Camion::create(['camion_id' => 'CAM-901', 'patente' => 'ZZ9901', 'capacidad_kg' => 3000]);
        }
        $conductor = Conductor::create(['rut' => (string) Rut::desdeNumero(++$this->secuenciaRut), 'nombre' => 'Conductor de prueba']);

        $despacho = Despacho::create([
            'camion_id' => 'CAM-901',
            'id_conductor' => $conductor->id_conductor,
            'estado' => 'en_ruta',
            'fecha_programada' => now()->toDateString(),
            'salida_real' => now()->subHours(2),
        ]);

        foreach ($clientes as $orden => $cliente) {
            $pedido = $this->pedido($cliente, 'portal', neto: 50000, estado: EstadoPedido::EN_RUTA);
            DespachoPedido::create([
                'id_despacho' => $despacho->id_despacho,
                'id_pedido' => $pedido->id_pedido,
                'orden_parada' => $orden + 1,
                'estado_entrega' => 'pendiente',
            ]);
        }

        $lectura = Telemetria::create([
            'camion_id' => 'CAM-901',
            'fecha_hora' => now()->subMinutes(20),
            'temp_camara' => 8.4,
            'lat' => -38.73,
            'long' => -72.59,
            'combustible_pct' => 55,
            'alerta_ruptura_frio' => true,
        ]);

        return Alerta::create(['id_telemetria' => $lectura->id_telemetria, 'tipo' => 'ruptura_frio', 'severidad' => 'alta', 'estado' => 'abierta']);
    }
}
