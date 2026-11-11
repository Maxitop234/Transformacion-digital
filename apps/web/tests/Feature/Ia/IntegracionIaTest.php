<?php

namespace Tests\Feature\Ia;

use App\Domain\Crm\CrearTicket;
use App\Domain\Ia\ClasificarTicket;
use App\Domain\Ia\ClienteIa;
use App\Domain\Ia\ClienteIaCompatibleOpenAi;
use App\Domain\Ia\FiltroDatosPersonales;
use App\Domain\Ia\IaNoDisponible;
use App\Models\ContactoCliente;
use App\Models\EstadoPedido;
use App\Models\Pedido;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\ClienteIaFalso;
use Tests\Support\CreaUsuarios;
use Tests\Support\CreaVentas;
use Tests\TestCase;

/** Integración de IA (T-021, RN-11). Nunca llama a la API real. Datos ficticios. */
class IntegracionIaTest extends TestCase
{
    use CreaUsuarios;
    use CreaVentas;
    use RefreshDatabase;

    private ClienteIaFalso $ia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVentas();
        $this->productoVenta('TST-IA', publico: 12000, convenio: 80000, nombre: 'Yogur natural de prueba');
        $this->ia = new ClienteIaFalso;
        $this->app->instance(ClienteIa::class, $this->ia);
    }

    // --- Filtro de datos personales (RN-11) -------------------------------------

    public function test_rn11_el_filtro_enmascara_rut_correos_telefonos_y_nombres(): void
    {
        $texto = 'Soy Camila Prueba, RUT 45.123.456-K (o 45123456-K), escribe a camila.prueba@correo.example '
            .'o llama al +56 9 5555 0123 o al (45) 221 2345. Pedido 1042 por $150.000.';

        $limpio = app(FiltroDatosPersonales::class)->enmascarar($texto, ['Camila Prueba']);

        foreach (['45.123.456-K', '45123456-K', 'camila.prueba@correo.example', '5555 0123', '221 2345', 'Camila Prueba'] as $dato) {
            $this->assertStringNotContainsString($dato, $limpio);
        }
        $this->assertStringContainsString('[rut]', $limpio);
        $this->assertStringContainsString('[correo]', $limpio);
        $this->assertStringContainsString('[telefono]', $limpio);
        $this->assertStringContainsString('[nombre]', $limpio);
        // Lo que no es personal se conserva: número de pedido y montos.
        $this->assertStringContainsString('Pedido 1042 por $150.000', $limpio);
    }

    // --- Clasificación de tickets -------------------------------------------------

    public function test_rn11_un_ticket_del_portal_queda_clasificado_por_la_ia_sin_enviar_datos_personales(): void
    {
        $cliente = $this->compradorCon('B2B');
        $cliente->cliente->update(['nombre_razon_social' => 'Clínica Ficticia del Sur']);
        ContactoCliente::create(['id_cliente' => $cliente->cliente->id_cliente, 'nombre' => 'Rosa Inventada', 'email' => 'rosa@clinica.example', 'es_principal' => true]);
        $pedido = $this->pedidoDe($cliente);
        $this->ia->responder(['categoria' => 'reclamo', 'prioridad' => 'alta']);

        $this->actingAs($cliente)->post('/tickets', [
            'asunto' => 'Carga tibia en Clínica Ficticia del Sur',
            'descripcion' => 'Llegó a 9 °C. Contactar a Rosa Inventada, RUT 45.987.654-3, rosa@clinica.example, +56 9 5555 0456.',
            'id_pedido' => $pedido->id_pedido,
        ])->assertRedirect();

        $ticket = Ticket::sole();
        $this->assertSame(['reclamo', 'alta', true], [$ticket->categoria, $ticket->prioridad, $ticket->clasificado_por_ia]);

        $enviado = $this->ia->enviados[0]['usuario'];
        foreach (['45.987.654-3', 'rosa@clinica.example', '5555 0456', 'Rosa Inventada', 'Clínica Ficticia del Sur', (string) $cliente->cliente->id_cliente] as $dato) {
            $this->assertStringNotContainsString($dato, $enviado, "Se envió a la IA: {$dato}");
        }
        $this->assertStringContainsString('"tiene_pedido_asociado":true', $enviado);
        $this->assertStringContainsString('<ticket>', $enviado);
    }

    public function test_con_la_api_caida_el_ticket_se_crea_igual_y_queda_sin_clasificar(): void
    {
        $this->ia->fallar();
        $cliente = $this->compradorCon('B2C');

        $this->actingAs($cliente)->post('/tickets', ['asunto' => 'Consulta', 'descripcion' => '¿Despachan a Villarrica?'])
            ->assertRedirect('/tickets')->assertSessionHas('estado');

        $ticket = Ticket::sole();
        $this->assertSame([Ticket::SIN_CLASIFICAR, false], [$ticket->categoria, $ticket->clasificado_por_ia]);
        $this->assertCount(1, $this->ia->enviados);
    }

    public function test_con_la_api_real_caida_o_lenta_se_lanza_ia_no_disponible(): void
    {
        config(['ia.clave' => 'clave-de-prueba']);
        $cliente = new ClienteIaCompatibleOpenAi;

        Http::fake(['*' => Http::response(['error' => 'rate limit'], 429)]);
        $this->assertLanza(fn () => $cliente->completarJson('s', 'u', 'e', []));

        Http::fake(['*' => fn () => throw new ConnectionException('timeout')]);
        $this->assertLanza(fn () => $cliente->completarJson('s', 'u', 'e', []));

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'esto no es json']]]])]);
        $this->assertLanza(fn () => $cliente->completarJson('s', 'u', 'e', []));

        config(['ia.clave' => '']);
        $this->assertLanza(fn () => $cliente->completarJson('s', 'u', 'e', []));
    }

    public function test_el_cliente_real_pide_json_estricto_con_la_clave_en_la_cabecera(): void
    {
        config(['ia.clave' => 'clave-de-prueba', 'ia.url_base' => 'https://ia.example/v1', 'ia.modelo' => 'modelo-x']);
        Http::fake(['https://ia.example/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '{"categoria":"consulta","prioridad":"baja"}']]],
        ])]);

        $datos = (new ClienteIaCompatibleOpenAi)->completarJson('sistema', 'usuario', 'clasificacion_ticket', ClasificarTicket::esquema());

        $this->assertSame(['categoria' => 'consulta', 'prioridad' => 'baja'], $datos);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer clave-de-prueba')
            && $r['model'] === 'modelo-x'
            && $r['temperature'] === 0
            && $r['response_format']['type'] === 'json_schema'
            && $r['response_format']['json_schema']['strict'] === true);
    }

    public function test_una_categoria_fuera_de_lista_se_descarta(): void
    {
        // «calidad» aparecía en el ejemplo antiguo de API.md, pero no existe en DATA_MODEL.md.
        $this->ia->responder(['categoria' => 'calidad', 'prioridad' => 'alta']);
        $ticket = $this->ticketPortal();

        $this->assertSame([Ticket::SIN_CLASIFICAR, 'media', false], [$ticket->categoria, $ticket->prioridad, $ticket->clasificado_por_ia]);

        $this->ia->responder(['categoria' => 'reclamo', 'prioridad' => 'urgentísima']);
        $otro = $this->ticketPortal();
        $this->assertSame([Ticket::SIN_CLASIFICAR, false], [$otro->categoria, $otro->clasificado_por_ia]);
    }

    public function test_la_ia_no_toca_tickets_ya_clasificados_por_regla_o_a_mano(): void
    {
        $cliente = $this->compradorCon('B2C');
        $deRegla = app(CrearTicket::class)($cliente->cliente, 'Carga rechazada', 'Motivo', null, 'reclamo', 'alta');

        $this->assertCount(0, $this->ia->enviados);
        $this->assertFalse($deRegla->refresh()->clasificado_por_ia);

        // Clasificado a mano mientras esperaba en la cola: la IA no lo pisa.
        $this->ia->fallar();
        $manual = $this->ticketPortal();
        $manual->update(['categoria' => 'consulta', 'prioridad' => 'baja']);
        $this->ia->responder(['categoria' => 'reclamo', 'prioridad' => 'alta']);

        $this->assertFalse(app(ClasificarTicket::class)($manual->id_ticket));
        $this->assertSame(['consulta', false], [$manual->refresh()->categoria, $manual->clasificado_por_ia]);
    }

    // --- Asistente del portal -------------------------------------------------------

    public function test_rn11_el_asistente_responde_el_estado_de_un_pedido_propio(): void
    {
        $cliente = $this->compradorCon('B2C');
        $pedido = $this->pedidoDe($cliente);
        $this->ia->responder(['respuesta' => "Tu pedido {$pedido->id_pedido} está Pendiente de pago.", 'derivar_a_ticket' => false]);

        $this->actingAs($cliente)->postJson('/asistente/mensajes', ['mensaje' => "¿En qué va mi pedido {$pedido->id_pedido}?"])
            ->assertOk()
            ->assertExactJson(['respuesta' => "Tu pedido {$pedido->id_pedido} está Pendiente de pago.", 'derivado_a_ticket' => false, 'id_ticket' => null]);

        $contexto = $this->contextoEnviado();
        $this->assertSame($pedido->id_pedido, $contexto['pedidos'][0]['id_pedido']);
        $this->assertSame(EstadoPedido::PENDIENTE, $contexto['pedidos'][0]['estado']);
        $this->assertSame([], $contexto['pedidos_no_encontrados']);
        $this->assertSame(0, Ticket::count());
    }

    public function test_el_asistente_no_entrega_datos_de_pedidos_ajenos(): void
    {
        $duena = $this->compradorCon('B2B');
        $duena->cliente->update(['nombre_razon_social' => 'Empresa Ajena Ficticia']);
        $ajeno = $this->pedidoDe($duena);
        $curioso = $this->compradorCon('B2C');
        $this->ia->responder(['respuesta' => 'No encuentro ese pedido entre los tuyos.', 'derivar_a_ticket' => false]);

        $this->actingAs($curioso)->postJson('/asistente/mensajes', ['mensaje' => "¿Cuál es el estado del pedido {$ajeno->id_pedido}?"])
            ->assertOk()->assertJsonPath('derivado_a_ticket', false);

        $contexto = $this->contextoEnviado();
        $this->assertSame([], $contexto['pedidos']);
        $this->assertSame([$ajeno->id_pedido], $contexto['pedidos_no_encontrados']);
        $enviado = $this->ia->enviados[0]['usuario'];
        $this->assertStringNotContainsString('Empresa Ajena Ficticia', $enviado);
        $this->assertStringNotContainsString(EstadoPedido::PENDIENTE, $enviado);
        $this->assertStringNotContainsString($ajeno->fecha_entrega_solicitada->format('Y-m-d'), $enviado);
    }

    public function test_si_la_ia_falla_o_no_resuelve_el_asistente_deriva_a_un_ticket(): void
    {
        $cliente = $this->compradorCon('B2C');
        $this->ia->fallar();

        $respuesta = $this->actingAs($cliente)->postJson('/asistente/mensajes', ['mensaje' => 'Necesito factura con otro giro'])
            ->assertOk()->assertJsonPath('derivado_a_ticket', true);

        $ticket = Ticket::sole();
        $this->assertSame($ticket->id_ticket, $respuesta->json('id_ticket'));
        $this->assertSame(['Consulta desde el asistente', 'Necesito factura con otro giro', $cliente->cliente->id_cliente], [$ticket->asunto, $ticket->descripcion, $ticket->id_cliente]);

        $this->ia->responder(['respuesta' => 'Un ejecutivo lo revisará.', 'derivar_a_ticket' => true]);
        $this->actingAs($cliente)->postJson('/asistente/mensajes', ['mensaje' => 'Quiero hablar con una persona'])
            ->assertOk()->assertJsonPath('derivado_a_ticket', true);
        $this->assertSame(2, Ticket::where('asunto', 'Consulta desde el asistente')->count());
    }

    public function test_el_asistente_tiene_limite_por_usuario_y_es_solo_para_clientes(): void
    {
        config(['ia.asistente_por_minuto' => 2]);
        $cliente = $this->compradorCon('B2C');
        $otro = $this->compradorCon('B2C');

        foreach (range(1, 3) as $i) {
            $this->ia->responder(['respuesta' => 'Hola.', 'derivar_a_ticket' => false])->responder(['respuesta' => 'Hola.', 'derivar_a_ticket' => false]);
        }

        $this->actingAs($cliente)->postJson('/asistente/mensajes', ['mensaje' => 'Hola'])->assertOk();
        $this->actingAs($cliente)->postJson('/asistente/mensajes', ['mensaje' => 'Hola'])->assertOk();
        $this->actingAs($cliente)->postJson('/asistente/mensajes', ['mensaje' => 'Hola'])->assertStatus(429);
        $this->actingAs($otro)->postJson('/asistente/mensajes', ['mensaje' => 'Hola'])->assertOk();

        $this->actingAs($this->vendedor())->postJson('/asistente/mensajes', ['mensaje' => 'Hola'])->assertForbidden();
        $this->actingAs($this->compradorCon('B2C'))->postJson('/asistente/mensajes', ['mensaje' => ''])->assertStatus(422);
    }

    // --- Apoyo ----------------------------------------------------------------------

    private function pedidoDe(Usuario $cliente): Pedido
    {
        $this->ia->responder(['categoria' => 'consulta', 'prioridad' => 'baja']); // por si algún paso crea un ticket
        $this->actingAs($cliente)->post('/carrito/items', ['sku' => 'TST-IA', 'cantidad' => $cliente->cliente->tipoCliente->codigo === 'B2B' ? 2 : 3])->assertSessionHasNoErrors();
        $this->actingAs($cliente)->post('/pedidos', $this->datosCheckout($cliente))->assertSessionHasNoErrors();
        $this->ia = new ClienteIaFalso;
        $this->app->instance(ClienteIa::class, $this->ia);

        return Pedido::latest('id_pedido')->firstOrFail();
    }

    private function ticketPortal(): Ticket
    {
        $cliente = $this->compradorCon('B2C');
        $this->actingAs($cliente)->post('/tickets', ['asunto' => 'Problema', 'descripcion' => 'Llegó mal.'])->assertRedirect();

        return Ticket::latest('id_ticket')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function contextoEnviado(): array
    {
        preg_match('/<contexto>\n(.*)\n<\/contexto>/s', $this->ia->enviados[0]['usuario'], $m);

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    private function assertLanza(callable $llamada): void
    {
        try {
            $llamada();
            $this->fail('Se esperaba IaNoDisponible.');
        } catch (IaNoDisponible) {
            $this->addToAssertionCount(1);
        }
    }
}
