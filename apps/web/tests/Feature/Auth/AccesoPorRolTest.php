<?php

namespace Tests\Feature\Auth;

use App\Models\Cliente;
use App\Models\Comuna;
use App\Models\DireccionDespacho;
use App\Models\EstadoPedido;
use App\Models\EtapaPipeline;
use App\Models\Oportunidad;
use App\Models\Pedido;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreaUsuarios;
use Tests\TestCase;

class AccesoPorRolTest extends TestCase
{
    use CreaUsuarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
    }

    public function test_un_visitante_que_abre_el_crm_va_al_inicio_de_sesion(): void
    {
        $this->get('/crm')->assertRedirect('/login');
    }

    public function test_un_cliente_que_abre_el_crm_recibe_403(): void
    {
        foreach ([Rol::CLIENTE_B2B, Rol::CLIENTE_B2C] as $rol) {
            $cliente = $this->usuario($rol);

            $this->actingAs($cliente)->get('/crm')->assertForbidden();
            $this->actingAs($cliente)->get('/crm/clientes')->assertForbidden();
        }
    }

    public function test_el_personal_entra_al_crm(): void
    {
        foreach (Rol::PERSONAL as $rol) {
            $this->actingAs($this->usuario($rol))->get('/crm')->assertOk();
        }
    }

    public function test_el_despachador_no_accede_a_oportunidades(): void
    {
        $despachador = $this->usuario(Rol::DESPACHADOR);

        $this->actingAs($despachador)->get('/crm/oportunidades')->assertForbidden();
        $this->actingAs($despachador)->get('/crm/clientes')->assertForbidden();
        $this->actingAs($despachador)->get('/crm/despachos')->assertOk();

        $this->actingAs($this->vendedor())->get('/crm/oportunidades')->assertOk();
    }

    public function test_solo_el_administrador_entra_a_administracion(): void
    {
        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->get('/crm/admin')->assertOk();
        $this->actingAs($this->vendedor())->get('/crm/admin')->assertForbidden();
        $this->actingAs($this->usuario(Rol::DESPACHADOR))->get('/crm/admin')->assertForbidden();
    }

    public function test_el_menu_del_crm_depende_del_rol(): void
    {
        $this->actingAs($this->usuario(Rol::DESPACHADOR))->get('/crm')
            ->assertSee('Despachos')->assertSee('Flota')
            ->assertDontSee('Oportunidades')->assertDontSee('Clientes')->assertDontSee('Administración');

        $this->actingAs($this->vendedor())->get('/crm')
            ->assertSee('Clientes')->assertSee('Oportunidades')->assertSee('Tickets')
            ->assertDontSee('Despachos')->assertDontSee('Administración');

        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->get('/crm')
            ->assertSee('Clientes')->assertSee('Despachos')->assertSee('Administración');
    }

    public function test_rn10_el_vendedor_solo_ve_y_abre_su_cartera(): void
    {
        $ana = $this->vendedor('Ana Vendedora');
        $beto = $this->vendedor('Beto Vendedor');
        $deAna = $this->clienteDe($ana, 'Casino Demo de Ana');
        $deBeto = $this->clienteDe($beto, 'Clínica Demo de Beto');

        $this->actingAs($ana)->get('/crm/clientes')
            ->assertOk()->assertSee('Casino Demo de Ana')->assertDontSee('Clínica Demo de Beto');

        $this->actingAs($ana)->get("/crm/clientes/{$deAna->id_cliente}")->assertOk()->assertSee('Casino Demo de Ana');
        // Un cliente de otra cartera no revela que existe.
        $this->actingAs($ana)->get("/crm/clientes/{$deBeto->id_cliente}")->assertNotFound();
    }

    public function test_rn10_el_administrador_ve_todas_las_carteras(): void
    {
        $ana = $this->vendedor('Ana Vendedora');
        $beto = $this->vendedor('Beto Vendedor');
        $this->clienteDe($ana, 'Casino Demo de Ana');
        $deBeto = $this->clienteDe($beto, 'Clínica Demo de Beto');
        $admin = $this->usuario(Rol::ADMINISTRADOR);

        $this->actingAs($admin)->get('/crm/clientes')->assertSee('Casino Demo de Ana')->assertSee('Clínica Demo de Beto');
        $this->actingAs($admin)->get("/crm/clientes/{$deBeto->id_cliente}")->assertOk();
    }

    public function test_rn10_el_alcance_de_cartera_se_reutiliza_en_las_tablas_relacionadas(): void
    {
        $ana = $this->vendedor('Ana Vendedora');
        $beto = $this->vendedor('Beto Vendedor');
        $deAna = $this->clienteDe($ana, 'Casino Demo de Ana');
        $deBeto = $this->clienteDe($beto, 'Clínica Demo de Beto');

        foreach ([[$deAna, $ana], [$deBeto, $beto]] as [$cliente, $vendedor]) {
            Oportunidad::create([
                'id_cliente' => $cliente->id_cliente,
                'id_vendedor' => $vendedor->vendedor->id_vendedor,
                'id_etapa' => EtapaPipeline::where('orden', 1)->value('id_etapa'),
                'titulo' => 'Oportunidad de '.$cliente->nombre_razon_social,
                'origen' => 'vendedor',
            ]);
            $this->pedidoDe($cliente);
        }

        $this->assertSame([$deAna->id_cliente], Oportunidad::visiblePara($ana)->pluck('id_cliente')->all());
        $this->assertSame([$deAna->id_cliente], Pedido::visiblePara($ana)->with('direccionDespacho')->get()->pluck('direccionDespacho.id_cliente')->all());
        $this->assertSame(2, Oportunidad::visiblePara($this->usuario(Rol::ADMINISTRADOR))->count());
        $this->assertSame(2, Pedido::visiblePara($this->usuario(Rol::DESPACHADOR))->count());
    }

    public function test_un_cliente_del_portal_solo_se_ve_a_si_mismo(): void
    {
        $cuenta = $this->usuario(Rol::CLIENTE_B2B);
        $propio = $this->clienteDe(null, 'Empresa Demo propia', $cuenta);
        $ajeno = $this->clienteDe(null, 'Empresa Demo ajena');
        $this->pedidoDe($propio);
        $this->pedidoDe($ajeno);

        $this->assertSame([$propio->id_cliente], Cliente::visiblePara($cuenta)->pluck('id_cliente')->all());
        $this->assertSame(1, Pedido::visiblePara($cuenta)->count());
        $this->assertSame(0, Cliente::visiblePara($this->usuario(Rol::CLIENTE_B2C))->count());
    }

    private function pedidoDe(Cliente $cliente): Pedido
    {
        $comuna = Comuna::first();
        $direccion = DireccionDespacho::create([
            'id_cliente' => $cliente->id_cliente,
            'id_comuna' => $comuna->id_comuna,
            'alias' => 'Bodega',
            'calle' => 'Pasaje Demo 1',
        ]);

        return Pedido::create([
            'id_direccion' => $direccion->id_direccion,
            'id_estado_pedido' => EstadoPedido::where('orden', 1)->value('id_estado_pedido'),
            'canal' => 'portal',
        ]);
    }
}
