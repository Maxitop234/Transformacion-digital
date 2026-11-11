<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\CrearTicket;
use App\Events\TicketCreado;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\CreaUsuarios;
use Tests\Support\CreaVentas;
use Tests\TestCase;

/** Tickets de requerimientos (T-018). Datos ficticios. */
class TicketsTest extends TestCase
{
    use CreaUsuarios;
    use CreaVentas;
    use RefreshDatabase;

    private Usuario $ana;

    private Usuario $beto;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVentas();
        $this->ana = $this->vendedor('Ana Vendedora');
        $this->beto = $this->vendedor('Beto Vendedor');
        $this->admin = $this->usuario(Rol::ADMINISTRADOR);
        $this->productoVenta('TST-TK', publico: 12000, convenio: 80000, nombre: 'Yogur de prueba');
    }

    // --- Creación desde el portal (RN-11) --------------------------------------

    public function test_rn11_un_ticket_nuevo_nace_sin_clasificar_y_sin_ia(): void
    {
        Event::fake([TicketCreado::class]);
        $cliente = $this->compradorCon('B2C', $this->ana);
        $pedido = $this->pedidoDe($cliente);

        $this->actingAs($cliente)->post('/tickets', [
            'asunto' => 'Llegó un yogur abierto',
            'descripcion' => 'Uno de los envases venía sin sello.',
            'id_pedido' => $pedido->id_pedido,
        ])->assertRedirect("/mis-pedidos/{$pedido->id_pedido}");

        $ticket = Ticket::sole();
        $this->assertSame(
            [Ticket::SIN_CLASIFICAR, 'media', 'abierto', false, $cliente->cliente->id_cliente, $pedido->id_pedido, $this->ana->vendedor->id_vendedor, null],
            [$ticket->categoria, $ticket->prioridad, $ticket->estado, $ticket->clasificado_por_ia, $ticket->id_cliente, $ticket->id_pedido, $ticket->id_vendedor, $ticket->cerrado_en],
        );
        $this->assertNotNull($ticket->creado_en);
        Event::assertDispatched(TicketCreado::class, fn (TicketCreado $e) => $e->idTicket === $ticket->id_ticket);
    }

    public function test_un_ticket_puede_crearse_sin_pedido(): void
    {
        $cliente = $this->compradorCon('B2B', $this->ana);

        $this->actingAs($cliente)->post('/tickets', ['asunto' => 'Cotizar helados', 'descripcion' => 'Necesitamos precio para 200 unidades.'])
            ->assertRedirect('/tickets');

        $this->assertNull(Ticket::sole()->id_pedido);
        $this->actingAs($cliente)->get('/tickets')->assertOk()->assertSee('Cotizar helados');
    }

    public function test_un_cliente_no_puede_crear_un_ticket_sobre_un_pedido_ajeno(): void
    {
        Event::fake([TicketCreado::class]);
        $duena = $this->compradorCon('B2C', $this->ana);
        $pedidoAjeno = $this->pedidoDe($duena);
        $intruso = $this->compradorCon('B2C', $this->beto);

        $this->actingAs($intruso)->post('/tickets', [
            'asunto' => 'Reclamo', 'descripcion' => 'Sobre un pedido que no es mío.', 'id_pedido' => $pedidoAjeno->id_pedido,
        ])->assertNotFound();

        $this->assertSame(0, Ticket::count());
        Event::assertNotDispatched(TicketCreado::class);

        // El dominio tampoco acepta un pedido de otro cliente.
        $this->expectException(\InvalidArgumentException::class);
        app(CrearTicket::class)($intruso->cliente, 'X', 'Y', $pedidoAjeno);
    }

    public function test_el_cliente_ve_solo_sus_tickets_y_el_personal_no_crea_desde_el_portal(): void
    {
        $uno = $this->compradorCon('B2C', $this->ana);
        $otro = $this->compradorCon('B2C', $this->ana);
        $this->actingAs($otro)->post('/tickets', ['asunto' => 'Asunto ajeno de prueba', 'descripcion' => 'Texto.'])->assertRedirect();

        $this->actingAs($uno)->get('/tickets')->assertOk()->assertDontSee('Asunto ajeno de prueba');
        $this->actingAs($this->ana)->post('/tickets', ['asunto' => 'A', 'descripcion' => 'B'])->assertForbidden();
    }

    // --- Visibilidad: ficha del cliente y detalle del pedido --------------------

    public function test_el_ticket_aparece_en_la_ficha_del_cliente_y_en_el_detalle_del_pedido(): void
    {
        $cliente = $this->compradorCon('B2C', $this->ana);
        $pedido = $this->pedidoDe($cliente);
        $this->actingAs($cliente)->post('/tickets', ['asunto' => 'Faltó una caja', 'descripcion' => 'Recibimos una caja menos.', 'id_pedido' => $pedido->id_pedido]);
        $ticket = Ticket::sole();
        $marca = "data-ticket=\"{$ticket->id_ticket}\"";

        $this->actingAs($this->ana)->get("/crm/clientes/{$cliente->cliente->id_cliente}")->assertOk()->assertSee($marca, false)->assertSee('Faltó una caja');
        $this->actingAs($this->ana)->get("/crm/pedidos/{$pedido->id_pedido}")->assertOk()->assertSee($marca, false)->assertSee('Faltó una caja');
        $this->actingAs($cliente)->get("/mis-pedidos/{$pedido->id_pedido}")->assertOk()->assertSee($marca, false);
    }

    // --- Bandeja del CRM (RN-10) ------------------------------------------------

    public function test_rn10_la_bandeja_filtra_por_cartera_categoria_prioridad_y_estado(): void
    {
        $deAna = $this->ticketPara($this->compradorCon('B2C', $this->ana), 'Ticket de Ana');
        $deBeto = $this->ticketPara($this->compradorCon('B2C', $this->beto), 'Ticket de Beto');
        $reclamo = $this->ticketPara($this->compradorCon('B2C', $this->ana), 'Reclamo urgente', 'reclamo', 'alta');

        $this->actingAs($this->ana)->get('/crm/tickets')->assertOk()
            ->assertSee('Ticket de Ana')->assertSee('Reclamo urgente')->assertDontSee('Ticket de Beto');
        $this->actingAs($this->ana)->get("/crm/tickets/{$deBeto->id_ticket}")->assertNotFound();
        $this->actingAs($this->ana)->patch("/crm/tickets/{$deBeto->id_ticket}", ['estado' => 'en_proceso'])->assertNotFound();

        $this->actingAs($this->admin)->get('/crm/tickets?categoria=reclamo&prioridad=alta')->assertOk()
            ->assertSee('Reclamo urgente')->assertDontSee('Ticket de Ana')->assertDontSee('Ticket de Beto');
        $this->actingAs($this->admin)->get('/crm/tickets?categoria=sin_clasificar')->assertOk()
            ->assertSee('Ticket de Ana')->assertSee('Ticket de Beto')->assertDontSee('Reclamo urgente');

        $deAna->update(['estado' => 'resuelto', 'categoria' => 'consulta']);
        $this->actingAs($this->admin)->get('/crm/tickets')->assertOk()->assertDontSee('Ticket de Ana');
        $this->actingAs($this->admin)->get('/crm/tickets?estado=resuelto')->assertOk()->assertSee('Ticket de Ana')->assertDontSee('Reclamo urgente');

        $this->actingAs($this->usuario(Rol::DESPACHADOR))->get('/crm/tickets')->assertForbidden();
        $this->actingAs($this->compradorCon('B2C'))->get('/crm/tickets')->assertForbidden();
        $this->assertNotNull($reclamo);
    }

    // --- Clasificación, asignación y cierre (RN-11) -------------------------------

    public function test_rn11_reclasificar_a_mano_deja_clasificado_por_ia_en_falso(): void
    {
        $ticket = $this->ticketPara($this->compradorCon('B2C', $this->ana), 'Consulta de horario');
        $ticket->update(['categoria' => 'consulta', 'prioridad' => 'baja', 'clasificado_por_ia' => true]);

        $this->actingAs($this->ana)->patch("/crm/tickets/{$ticket->id_ticket}", ['categoria' => 'reclamo', 'prioridad' => 'alta'])
            ->assertRedirect("/crm/tickets/{$ticket->id_ticket}");

        $ticket->refresh();
        $this->assertSame(['reclamo', 'alta', false], [$ticket->categoria, $ticket->prioridad, $ticket->clasificado_por_ia]);

        $this->actingAs($this->ana)->patch("/crm/tickets/{$ticket->id_ticket}", ['categoria' => 'sin_clasificar'])->assertSessionHasErrors('categoria');
    }

    public function test_cerrar_un_ticket_registra_cerrado_en_y_luego_no_cambia(): void
    {
        $ticket = $this->ticketPara($this->compradorCon('B2C', $this->ana), 'Para cerrar');

        // Sin categoría no se puede cerrar.
        $this->actingAs($this->ana)->patch("/crm/tickets/{$ticket->id_ticket}", ['estado' => 'cerrado'])->assertSessionHasErrors('categoria');
        $this->assertNull($ticket->refresh()->cerrado_en);

        $this->actingAs($this->ana)->patch("/crm/tickets/{$ticket->id_ticket}", ['categoria' => 'consulta', 'estado' => 'cerrado'])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('cerrado', $ticket->estado);
        $this->assertNotNull($ticket->cerrado_en);
        $this->assertTrue($ticket->cerrado_en->diffInSeconds(now(), true) < 60);

        $this->actingAs($this->ana)->patch("/crm/tickets/{$ticket->id_ticket}", ['estado' => 'abierto'])->assertStatus(409);
        $this->assertSame('cerrado', $ticket->refresh()->estado);
    }

    public function test_el_administrador_asigna_y_el_vendedor_solo_toma_para_si(): void
    {
        $ticket = $this->ticketPara($this->compradorCon('B2C'), 'Cliente sin cartera');
        $this->assertNull($ticket->id_vendedor);

        $this->actingAs($this->admin)->patch("/crm/tickets/{$ticket->id_ticket}", ['id_vendedor' => $this->beto->vendedor->id_vendedor])->assertRedirect();
        $this->assertSame($this->beto->vendedor->id_vendedor, $ticket->refresh()->id_vendedor);

        $deAna = $this->ticketPara($this->compradorCon('B2C', $this->ana), 'De la cartera de Ana');
        $this->actingAs($this->ana)->patch("/crm/tickets/{$deAna->id_ticket}", ['id_vendedor' => $this->beto->vendedor->id_vendedor])->assertForbidden();
        $this->assertSame($this->ana->vendedor->id_vendedor, $deAna->refresh()->id_vendedor);
    }

    // --- Carga rechazada ----------------------------------------------------------

    public function test_el_rechazo_de_una_carga_crea_el_ticket_por_el_mismo_servicio(): void
    {
        Event::fake([TicketCreado::class]);
        $ticket = app(CrearTicket::class)(
            $this->compradorCon('B2C', $this->ana)->cliente, 'Carga rechazada', 'Motivo de logística', null, categoria: 'reclamo', prioridad: 'alta',
        );

        $this->assertSame(['reclamo', 'alta', 'abierto', false], [$ticket->categoria, $ticket->prioridad, $ticket->estado, $ticket->clasificado_por_ia]);
        Event::assertDispatched(TicketCreado::class);
    }

    // --- Apoyo --------------------------------------------------------------------

    private function pedidoDe(Usuario $cliente): Pedido
    {
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-TK', 'cantidad' => $cliente->tieneRol(Rol::CLIENTE_B2B) ? 2 : 3])->assertSessionHasNoErrors();
        $this->actingAs($cliente)->post('/pedidos', $this->datosCheckout($cliente))->assertSessionHasNoErrors();

        return Pedido::latest('id_pedido')->firstOrFail();
    }

    private function ticketPara(Usuario $cliente, string $asunto, ?string $categoria = null, ?string $prioridad = null): Ticket
    {
        return app(CrearTicket::class)($cliente->cliente, $asunto, 'Descripción de prueba.', null, $categoria, $prioridad);
    }
}
