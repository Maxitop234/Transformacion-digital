<?php

namespace Tests\Feature\Crm;

use App\Domain\Clientes\Rut;
use App\Domain\Ventas\CalendarioEntregas;
use App\Models\Cliente;
use App\Models\ContactoCliente;
use App\Models\DetallePedido;
use App\Models\DireccionDespacho;
use App\Models\EstadoPedido;
use App\Models\EtapaPipeline;
use App\Models\HistorialEstadoPedido;
use App\Models\HistorialEtapa;
use App\Models\Interaccion;
use App\Models\Inventario;
use App\Models\Oportunidad;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreaUsuarios;
use Tests\Support\CreaVentas;
use Tests\TestCase;

/** Gestión de clientes y prospectos en el CRM (T-014). Datos ficticios. */
class GestionClientesTest extends TestCase
{
    use CreaUsuarios;
    use CreaVentas;
    use RefreshDatabase;

    private Usuario $ana;

    private Usuario $beto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVentas();
        $this->ana = $this->vendedor('Ana Vendedora');
        $this->beto = $this->vendedor('Beto Vendedor');
    }

    // --- Listado y ficha ------------------------------------------------------

    public function test_el_listado_busca_por_nombre_o_rut_y_filtra_por_tipo_y_estado_dentro_de_la_cartera(): void
    {
        $clinica = $this->compradorCon('B2B', $this->ana)->cliente;
        $clinica->update(['nombre_razon_social' => 'Clínica Demo Norte', 'rut' => Rut::desdeNumero(45_200_001)->normalizado()]);
        $persona = $this->compradorCon('B2C', $this->ana)->cliente;
        $persona->update(['nombre_razon_social' => 'Persona Demo Sur']);
        $ajena = $this->compradorCon('B2B', $this->beto)->cliente;
        $ajena->update(['nombre_razon_social' => 'Clínica Demo de Beto']);

        $this->actingAs($this->ana)->get('/crm/clientes?q=clínica')->assertOk()
            ->assertSee('Clínica Demo Norte')->assertDontSee('Persona Demo Sur')->assertDontSee('Clínica Demo de Beto');
        $this->actingAs($this->ana)->get('/crm/clientes?q=45.200.001')->assertSee('Clínica Demo Norte')->assertDontSee('Persona Demo Sur');
        $this->actingAs($this->ana)->get('/crm/clientes?tipo=B2C')->assertSee('Persona Demo Sur')->assertDontSee('Clínica Demo Norte');
        $this->actingAs($this->ana)->get('/crm/clientes?estado=prospecto')->assertDontSee('Persona Demo Sur')->assertDontSee('Clínica Demo Norte');
        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->get('/crm/clientes?q=clínica')->assertSee('Clínica Demo de Beto');
    }

    public function test_la_ficha_muestra_contactos_puntos_interacciones_pedidos_con_canal_y_tickets(): void
    {
        $this->productoVenta('TST-001', convenio: 200000);
        $cuenta = $this->compradorCon('B2B', $this->ana);
        $cliente = $cuenta->cliente;
        ContactoCliente::create(['id_cliente' => $cliente->id_cliente, 'nombre' => 'Contacto Ficticio', 'email' => 'contacto@empresa.example', 'es_principal' => true]);
        Interaccion::create(['id_cliente' => $cliente->id_cliente, 'id_vendedor' => $this->ana->vendedor->id_vendedor, 'tipo' => 'visita', 'resumen' => 'Visita de prueba a bodega', 'fecha_hora' => now()]);
        Ticket::create(['id_cliente' => $cliente->id_cliente, 'asunto' => 'Ticket de prueba', 'descripcion' => 'Texto']);
        $this->actingAs($this->ana)->post("/crm/clientes/{$cliente->id_cliente}/pedidos", $this->pedidoTelefonico($cliente, ['TST-001' => 1]));

        $this->actingAs($this->ana)->get("/crm/clientes/{$cliente->id_cliente}")->assertOk()
            ->assertSee('Contacto Ficticio')
            ->assertSee('Bodega de prueba')
            ->assertSee('Visita de prueba a bodega')
            ->assertSee('data-canal="crm"', false)
            ->assertSee('CRM (Ana Vendedora)')
            ->assertSee('Ticket de prueba');
    }

    // --- Prospectos -----------------------------------------------------------

    public function test_el_vendedor_ingresa_un_prospecto_en_su_cartera_con_su_oportunidad(): void
    {
        $this->actingAs($this->ana)->post('/crm/clientes/nuevo', [
            'tipo' => 'B2B',
            'nombre_razon_social' => 'Prospecto Demo Lagos',
            'rut' => '45.200.002-'.Rut::calcularDv(45_200_002),
            'origen' => 'telefono',
            'titulo_oportunidad' => 'Abastecimiento casino',
            'id_vendedor' => $this->beto->vendedor->id_vendedor, // se ignora: queda en su cartera
            'contacto_nombre' => 'Encargada Ficticia',
            'contacto_email' => 'compras@lagos.example',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $prospecto = Cliente::where('nombre_razon_social', 'Prospecto Demo Lagos')->sole();
        $this->assertSame(['prospecto', $this->ana->vendedor->id_vendedor, '45200002-'.Rut::calcularDv(45_200_002), null], [$prospecto->estado_cliente, $prospecto->id_vendedor, $prospecto->rut, $prospecto->id_usuario]);
        $this->assertTrue(ContactoCliente::where('id_cliente', $prospecto->id_cliente)->sole()->es_principal);

        $oportunidad = Oportunidad::where('id_cliente', $prospecto->id_cliente)->sole();
        $this->assertSame(['Prospecto nuevo', 'telefono', 'Abastecimiento casino'], [$oportunidad->etapaPipeline->nombre, $oportunidad->origen, $oportunidad->titulo]);
        $this->assertSame($this->ana->id_usuario, HistorialEtapa::where('id_oportunidad', $oportunidad->id_oportunidad)->sole()->id_usuario);
    }

    public function test_el_prospecto_exige_rut_valido_y_unico_y_el_administrador_elige_vendedor(): void
    {
        $base = ['tipo' => 'B2B', 'nombre_razon_social' => 'Prospecto X', 'origen' => 'vendedor', 'titulo_oportunidad' => 'Primer contacto'];

        $dvMalo = Rut::calcularDv(45_200_003) === '1' ? '2' : '1';
        $this->actingAs($this->ana)->post('/crm/clientes/nuevo', [...$base, 'rut' => "45200003-{$dvMalo}"])
            ->assertSessionHasErrors(['rut' => 'El RUT no es válido.']);

        $existente = $this->compradorCon('B2B', $this->beto)->cliente;
        $existente->update(['rut' => Rut::desdeNumero(45_200_004)->normalizado()]);
        $this->actingAs($this->ana)->post('/crm/clientes/nuevo', [...$base, 'rut' => Rut::desdeNumero(45_200_004)->formateado()])
            ->assertSessionHasErrors(['rut' => 'Ya existe un cliente con ese RUT.']);

        $admin = $this->usuario(Rol::ADMINISTRADOR);
        $this->actingAs($admin)->post('/crm/clientes/nuevo', $base)->assertSessionHasErrors('id_vendedor');
        $this->actingAs($admin)->post('/crm/clientes/nuevo', [...$base, 'id_vendedor' => $this->beto->vendedor->id_vendedor])->assertSessionHasNoErrors();
        $this->assertSame($this->beto->vendedor->id_vendedor, Cliente::where('nombre_razon_social', 'Prospecto X')->sole()->id_vendedor);
    }

    // --- Validación de empresas (RN-02) ----------------------------------------

    public function test_rn02_tras_la_validacion_la_empresa_ve_su_lista_institucional_y_puede_comprar(): void
    {
        $this->productoVenta('TST-001', publico: 10000, convenio: 8000);
        $empresa = $this->empresaPendiente($this->ana);

        // Antes: precio público y sin poder comprar.
        $this->actingAs($empresa)->get('/productos/TST-001')->assertSee('Público general')->assertSee('$10.000')->assertSee('data-agregar="TST-001" disabled', false);
        $this->actingAs($empresa)->post('/carrito/items', ['sku' => 'TST-001'])->assertSessionHasErrors('sku');

        $this->actingAs($this->ana)->get("/crm/clientes/{$empresa->cliente->id_cliente}")->assertSee('data-bloque="validar"', false);
        $this->actingAs($this->ana)->post("/crm/clientes/{$empresa->cliente->id_cliente}/validar", [
            'id_lista_precio' => $this->convenio->id_lista_precio,
            'id_vendedor' => $this->beto->vendedor->id_vendedor, // el vendedor no reasigna: queda él
        ])->assertRedirect()->assertSessionHasNoErrors();

        $cliente = $empresa->cliente->refresh();
        $this->assertSame(['activo', $this->convenio->id_lista_precio, $this->ana->vendedor->id_vendedor], [$cliente->estado_cliente, $cliente->id_lista_precio, $cliente->id_vendedor]);
        $this->assertNotNull($cliente->fecha_validacion);

        // Después: su lista institucional y puede comprar.
        $this->actingAs($empresa)->get('/productos/TST-001')->assertSee('Convenio de prueba')->assertSee('$8.000')->assertDontSee('data-agregar="TST-001" disabled', false);
        $this->actingAs($empresa)->post('/carrito/items', ['sku' => 'TST-001'])->assertSessionHasNoErrors();
    }

    public function test_rn02_la_validacion_exige_empresa_pendiente_con_rut_y_lista_institucional(): void
    {
        $pendiente = $this->empresaPendiente($this->ana)->cliente;
        $url = "/crm/clientes/{$pendiente->id_cliente}/validar";

        $this->actingAs($this->ana)->post($url, ['id_lista_precio' => $this->publica->id_lista_precio])->assertSessionHasErrors('id_lista_precio');

        $pendiente->update(['rut' => null]);
        $this->actingAs($this->ana)->post($url, ['id_lista_precio' => $this->convenio->id_lista_precio])->assertSessionHasErrors('rut');

        $activa = $this->compradorCon('B2B', $this->ana)->cliente;
        $this->actingAs($this->ana)->post("/crm/clientes/{$activa->id_cliente}/validar", ['id_lista_precio' => $this->convenio->id_lista_precio])->assertStatus(409);

        // El administrador sí puede asignar otro vendedor al validar.
        $otra = $this->empresaPendiente($this->ana)->cliente;
        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->post("/crm/clientes/{$otra->id_cliente}/validar", [
            'id_lista_precio' => $this->convenio->id_lista_precio,
            'id_vendedor' => $this->beto->vendedor->id_vendedor,
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->beto->vendedor->id_vendedor, $otra->refresh()->id_vendedor);
    }

    // --- Cartera (RN-10) -------------------------------------------------------

    public function test_rn10_un_vendedor_no_puede_validar_ni_abrir_un_cliente_de_otra_cartera(): void
    {
        $this->productoVenta('TST-001', convenio: 200000);
        $deBeto = $this->empresaPendiente($this->beto)->cliente;
        $activoDeBeto = $this->compradorCon('B2B', $this->beto)->cliente;

        $this->actingAs($this->ana)->get("/crm/clientes/{$deBeto->id_cliente}")->assertNotFound();
        $this->actingAs($this->ana)->post("/crm/clientes/{$deBeto->id_cliente}/validar", ['id_lista_precio' => $this->convenio->id_lista_precio])->assertNotFound();
        $this->actingAs($this->ana)->post("/crm/clientes/{$deBeto->id_cliente}/interacciones", ['tipo' => 'llamada', 'resumen' => 'x'])->assertNotFound();
        $this->actingAs($this->ana)->post("/crm/clientes/{$deBeto->id_cliente}/contactos", ['nombre' => 'X', 'email' => 'x@x.example'])->assertNotFound();
        $this->actingAs($this->ana)->get("/crm/clientes/{$activoDeBeto->id_cliente}/pedidos/nuevo")->assertNotFound();
        $this->actingAs($this->ana)->post("/crm/clientes/{$activoDeBeto->id_cliente}/pedidos", $this->pedidoTelefonico($activoDeBeto, ['TST-001' => 1]))->assertNotFound();

        $this->assertSame('pendiente_validacion', $deBeto->refresh()->estado_cliente);
        $this->assertSame(0, Interaccion::count() + ContactoCliente::count() + Pedido::count());

        // El despachador no entra a la gestión comercial.
        $this->actingAs($this->usuario(Rol::DESPACHADOR))->get("/crm/clientes/{$deBeto->id_cliente}")->assertForbidden();
    }

    // --- Interacciones y contactos -----------------------------------------------

    public function test_cada_interaccion_queda_con_fecha_tipo_y_vendedor(): void
    {
        $cliente = $this->compradorCon('B2B', $this->ana)->cliente;
        $oportunidad = Oportunidad::create(['id_cliente' => $cliente->id_cliente, 'id_vendedor' => $this->ana->vendedor->id_vendedor, 'id_etapa' => EtapaPipeline::where('nombre', 'Contactado')->value('id_etapa'), 'titulo' => 'Op de prueba', 'origen' => 'vendedor', 'fecha_creacion' => now()]);
        $ajena = Oportunidad::create(['id_cliente' => $this->compradorCon('B2B', $this->beto)->cliente->id_cliente, 'id_vendedor' => $this->beto->vendedor->id_vendedor, 'id_etapa' => EtapaPipeline::where('nombre', 'Contactado')->value('id_etapa'), 'titulo' => 'Op ajena', 'origen' => 'vendedor', 'fecha_creacion' => now()]);

        $this->travelTo(now()->setTime(10, 30));
        $this->actingAs($this->ana)->post("/crm/clientes/{$cliente->id_cliente}/interacciones", [
            'tipo' => 'reunion', 'resumen' => 'Reunión de prueba', 'id_oportunidad' => $oportunidad->id_oportunidad,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $interaccion = Interaccion::sole();
        $this->assertSame(['reunion', $this->ana->vendedor->id_vendedor, $oportunidad->id_oportunidad], [$interaccion->tipo, $interaccion->id_vendedor, $interaccion->id_oportunidad]);
        $this->assertTrue($interaccion->fecha_hora->equalTo(now()));

        // Oportunidad de otro cliente: rechazada. Tipo fuera de la lista: rechazado.
        $this->actingAs($this->ana)->post("/crm/clientes/{$cliente->id_cliente}/interacciones", ['tipo' => 'llamada', 'resumen' => 'x', 'id_oportunidad' => $ajena->id_oportunidad])->assertSessionHasErrors('id_oportunidad');
        $this->actingAs($this->ana)->post("/crm/clientes/{$cliente->id_cliente}/interacciones", ['tipo' => 'fax', 'resumen' => 'x'])->assertSessionHasErrors('tipo');

        // Si la registra el administrador, queda a nombre del vendedor del cliente.
        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->post("/crm/clientes/{$cliente->id_cliente}/interacciones", ['tipo' => 'llamada', 'resumen' => 'Llamada del administrador']);
        $this->assertSame($this->ana->vendedor->id_vendedor, Interaccion::where('resumen', 'Llamada del administrador')->sole()->id_vendedor);
        $this->assertSame(2, Interaccion::count());
    }

    public function test_un_nuevo_contacto_principal_reemplaza_al_anterior(): void
    {
        $cliente = $this->compradorCon('B2B', $this->ana)->cliente;

        $this->actingAs($this->ana)->post("/crm/clientes/{$cliente->id_cliente}/contactos", ['nombre' => 'Primera', 'email' => 'primera@empresa.example', 'es_principal' => 1]);
        $this->actingAs($this->ana)->post("/crm/clientes/{$cliente->id_cliente}/contactos", ['nombre' => 'Segunda', 'email' => 'segunda@empresa.example', 'es_principal' => 1])->assertSessionHasNoErrors();

        $this->assertSame(['Segunda'], ContactoCliente::where('es_principal', true)->pluck('nombre')->all());
        $this->assertSame(2, ContactoCliente::count());
    }

    // --- Pedido telefónico (RN-08) -----------------------------------------------

    public function test_rn08_el_pedido_telefonico_reutiliza_crear_pedido_y_respeta_minimo_stock_y_precio_de_lista(): void
    {
        $producto = $this->productoVenta('TST-001', publico: 90000, convenio: 70000, stock: 3);
        $cliente = $this->compradorCon('B2B', $this->ana)->cliente;
        $url = "/crm/clientes/{$cliente->id_cliente}/pedidos";

        // Mínimo B2B ($150.000 neto): 2 × 70.000 = 140.000 no alcanza.
        $this->actingAs($this->ana)->post($url, $this->pedidoTelefonico($cliente, ['TST-001' => 2]))
            ->assertSessionHasErrors(['pedido' => 'El pedido mínimo es $150.000 neto.']);

        // Stock: hay 3; pedir 4 no reserva nada.
        $this->actingAs($this->ana)->post($url, $this->pedidoTelefonico($cliente, ['TST-001' => 4]))
            ->assertSessionHasErrors(['pedido' => 'No queda stock suficiente de Producto de prueba.']);
        $this->assertSame(0, Pedido::count());

        // 3 × 70.000: precio de la lista institucional del cliente, no el público.
        $this->actingAs($this->ana)->post($url, $this->pedidoTelefonico($cliente, ['TST-001' => 3]))
            ->assertRedirect("/crm/clientes/{$cliente->id_cliente}")->assertSessionHasNoErrors();

        $pedido = Pedido::sole();
        $this->assertSame(['crm', $this->ana->vendedor->id_vendedor, null, EstadoPedido::PENDIENTE], [$pedido->canal, $pedido->id_vendedor_registra, $pedido->id_carrito, $pedido->estadoPedido->nombre]);
        $this->assertSame('70000.00', (string) DetallePedido::sole()->precio_unitario_pactado);
        $this->assertSame('3.00', (string) Inventario::find($producto->id_producto)->stock_reservado);
        $this->assertSame([$this->ana->id_usuario, 'Pedido telefónico ingresado en el CRM'], [HistorialEstadoPedido::sole()->id_usuario, HistorialEstadoPedido::sole()->comentario]);
        $this->assertSame(['credito_30d', 'pendiente', 'OC-TEL-1'], [Pago::sole()->metodo, Pago::sole()->estado, Pago::sole()->referencia_externa]);
    }

    public function test_rn08_el_pedido_telefonico_solo_es_para_clientes_que_pueden_comprar(): void
    {
        $this->productoVenta('TST-001', publico: 90000, convenio: 90000);
        $pendiente = $this->empresaPendiente($this->ana)->cliente;
        $this->direccionDe($pendiente);

        $this->actingAs($this->ana)->get("/crm/clientes/{$pendiente->id_cliente}/pedidos/nuevo")->assertOk()->assertSee('La cuenta del cliente aún no puede comprar.');
        $this->actingAs($this->ana)->post("/crm/clientes/{$pendiente->id_cliente}/pedidos", $this->pedidoTelefonico($pendiente, ['TST-001' => 2]))
            ->assertSessionHasErrors('pedido');
        $this->assertSame(0, Pedido::count());
    }

    // --- Apoyo ---------------------------------------------------------------

    /** Empresa con cuenta, RUT y punto de entrega, pendiente de validación. */
    private function empresaPendiente(Usuario $vendedor): Usuario
    {
        static $rut = 45_210_000;
        $cuenta = $this->compradorCon('B2B', $vendedor, 'pendiente_validacion');
        $cuenta->cliente->update(['id_lista_precio' => $this->publica->id_lista_precio, 'rut' => Rut::desdeNumero(++$rut)->normalizado()]);

        return $cuenta->refresh();
    }

    /** @param  array<string, int>  $porSku */
    private function pedidoTelefonico(Cliente $cliente, array $porSku): array
    {
        $lineas = [];
        foreach ($porSku as $sku => $cantidad) {
            $lineas[] = ['id_producto' => Producto::where('sku', $sku)->value('id_producto'), 'cantidad' => $cantidad];
        }

        return [
            'lineas' => $lineas,
            'id_direccion' => DireccionDespacho::where('id_cliente', $cliente->id_cliente)->value('id_direccion'),
            'fecha_entrega_solicitada' => app(CalendarioEntregas::class)->primeraFecha()->toDateString(),
            'orden_compra' => 'OC-TEL-1',
        ];
    }
}
