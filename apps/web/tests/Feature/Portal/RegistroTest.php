<?php

namespace Tests\Feature\Portal;

use App\Domain\Clientes\RegistrarCliente;
use App\Domain\Clientes\Rut;
use App\Models\Cliente;
use App\Models\Comuna;
use App\Models\ContactoCliente;
use App\Models\DireccionDespacho;
use App\Models\HistorialEtapa;
use App\Models\Oportunidad;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaUsuarios;
use Tests\Support\CreaVentas;
use Tests\TestCase;

/** Registro de clientes B2C y B2B (T-009). Datos ficticios. */
class RegistroTest extends TestCase
{
    use CreaUsuarios;
    use CreaVentas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVentas();
        $this->productoVenta('TST-REG', publico: 30000, convenio: 80000, nombre: 'Queso de prueba');
    }

    // --- Persona (B2C) ------------------------------------------------------------

    public function test_el_registro_b2c_crea_usuario_cliente_y_direccion_en_una_transaccion(): void
    {
        $this->post('/registro/persona', $this->datosPersona())
            ->assertRedirect('/catalogo')
            ->assertSessionHas('estado');

        $usuario = Usuario::where('email', 'paula.prueba@correo.example')->sole();
        $this->assertSame(Rol::CLIENTE_B2C, $usuario->nombreRol());
        $this->assertTrue($usuario->activo);
        $this->assertNotSame('Clave1234', $usuario->password_hash);
        $this->assertAuthenticatedAs($usuario);

        $cliente = Cliente::where('id_usuario', $usuario->id_usuario)->with(['tipoCliente', 'listaPrecio'])->sole();
        $this->assertSame(['B2C', 'activo', CatalogoSeeder::LISTA_PUBLICA, 'Paula Prueba', null], [
            $cliente->tipoCliente->codigo, $cliente->estado_cliente, $cliente->listaPrecio->nombre, $cliente->nombre_razon_social, $cliente->id_vendedor,
        ]);

        $this->assertTrue($cliente->consentimiento_datos_en->diffInSeconds(now(), true) < 60, 'Se guarda cuándo aceptó el tratamiento de datos.');

        $direccion = DireccionDespacho::where('id_cliente', $cliente->id_cliente)->sole();
        $this->assertSame(['Calle Inventada 742', true], [$direccion->calle, $direccion->activa]);
        $this->assertSame(0, Oportunidad::count());

        // Puede comprar de inmediato.
        $this->post('/carrito/items', ['sku' => 'TST-REG', 'cantidad' => 1])->assertSessionHasNoErrors();
    }

    public function test_si_falla_un_paso_del_registro_no_queda_nada_a_medias(): void
    {
        $usuarios = Usuario::count();

        try {
            // Comuna inexistente: la dirección falla después de crear usuario y cliente.
            app(RegistrarCliente::class)->persona([
                'nombre' => 'Ana Atómica', 'email' => 'ana@correo.example', 'password' => 'Clave1234',
                'direccion' => ['calle' => 'Calle 1', 'id_comuna' => 999999],
            ]);
            $this->fail('Se esperaba un error de la base.');
        } catch (QueryException) {
        }

        $this->assertSame($usuarios, Usuario::count());
        $this->assertSame(0, Cliente::where('nombre_razon_social', 'Ana Atómica')->count());
    }

    public function test_el_registro_exige_consentimiento_correo_unico_y_contrasena_confirmada(): void
    {
        $this->post('/registro/persona', $this->datosPersona(['consentimiento' => null]))
            ->assertSessionHasErrors(['consentimiento' => 'Debes aceptar el tratamiento de tus datos personales para crear la cuenta.']);
        $this->post('/registro/persona', $this->datosPersona(['password_confirmation' => 'Otra1234']))->assertSessionHasErrors('password');
        $this->post('/registro/persona', $this->datosPersona(['password' => 'corta', 'password_confirmation' => 'corta']))->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertSame(0, Usuario::where('email', 'paula.prueba@correo.example')->count());
        $this->assertSame(0, Cliente::whereNotNull('consentimiento_datos_en')->count());

        $this->post('/registro/persona', $this->datosPersona())->assertRedirect('/catalogo');
        Auth::logout();

        $this->post('/registro/persona', $this->datosPersona(['email' => 'PAULA.prueba@correo.example']))
            ->assertSessionHasErrors(['email' => 'Ya existe una cuenta con ese correo.']);
        $this->assertSame(1, Usuario::where('email', 'paula.prueba@correo.example')->count());
    }

    public function test_un_usuario_con_sesion_no_ve_el_registro(): void
    {
        $this->get('/registro/persona')->assertOk()->assertSee('data-registro="persona"', false);
        $this->get('/registro/empresa')->assertOk()->assertSee('data-registro="empresa"', false);

        $cliente = $this->compradorCon('B2C');
        $this->actingAs($cliente)->get('/registro/persona')->assertRedirect();
        $this->actingAs($cliente)->post('/registro/empresa', $this->datosEmpresa())->assertRedirect();
        $this->assertSame(0, Cliente::where('nombre_razon_social', 'Empresa Ficticia de Prueba SpA')->count());
    }

    // --- Empresa (B2B, RN-02) ------------------------------------------------------

    public function test_rn02_el_registro_b2b_crea_contacto_principal_y_oportunidad_asignada(): void
    {
        $cargado = $this->vendedor('Vendedor con cartera');
        $this->clienteDe($cargado, 'Cliente existente de prueba');
        $libre = $this->vendedor('Vendedor libre');
        $rut = Rut::desdeNumero(45_700_001);

        $this->post('/registro/empresa', $this->datosEmpresa(['rut' => $rut->formateado()]))
            ->assertRedirect('/catalogo');

        $usuario = Usuario::where('email', 'compras@empresa-ficticia.example')->sole();
        $this->assertSame(Rol::CLIENTE_B2B, $usuario->nombreRol());

        $cliente = Cliente::where('id_usuario', $usuario->id_usuario)->with('tipoCliente')->sole();
        $this->assertSame(['B2B', 'pendiente_validacion', $rut->normalizado(), $libre->vendedor->id_vendedor, null], [
            $cliente->tipoCliente->codigo, $cliente->estado_cliente, $cliente->rut, $cliente->id_vendedor, $cliente->fecha_validacion,
        ]);

        $this->assertNotNull($cliente->consentimiento_datos_en);

        $contacto = ContactoCliente::where('id_cliente', $cliente->id_cliente)->sole();
        $this->assertSame(['Tomás Inventado', 'Jefe de compras', 'compras@empresa-ficticia.example', true], [$contacto->nombre, $contacto->cargo, $contacto->email, $contacto->es_principal]);

        $direccion = DireccionDespacho::where('id_cliente', $cliente->id_cliente)->sole();
        $this->assertSame(['Casino central', '07:00:00', '11:00:00'], [$direccion->alias, $direccion->ventana_inicio, $direccion->ventana_fin]);

        $oportunidad = Oportunidad::where('id_cliente', $cliente->id_cliente)->with('etapaPipeline')->sole();
        $this->assertSame(['Prospecto nuevo', 'portal', $libre->vendedor->id_vendedor], [$oportunidad->etapaPipeline->nombre, $oportunidad->origen, $oportunidad->id_vendedor]);
        $this->assertSame(1, HistorialEtapa::where('id_oportunidad', $oportunidad->id_oportunidad)->count());

        // El vendedor asignado la ve como pendiente en su panel (RN-10).
        $this->actingAs($libre)->get('/crm')->assertOk()->assertSee('Empresa Ficticia de Prueba SpA');
        $this->actingAs($cargado)->get('/crm')->assertOk()->assertDontSee('Empresa Ficticia de Prueba SpA');
    }

    public function test_rn02_el_registro_b2b_exige_ventana_de_recepcion(): void
    {
        $this->vendedor();

        $this->post('/registro/empresa', $this->datosEmpresa(['ventana_inicio' => '', 'ventana_fin' => '']))
            ->assertSessionHasErrors(['ventana_inicio', 'ventana_fin']);
        $this->post('/registro/empresa', $this->datosEmpresa(['ventana_inicio' => '12:00', 'ventana_fin' => '08:00']))
            ->assertSessionHasErrors('ventana_fin');
        $this->assertSame(0, Cliente::where('nombre_razon_social', 'Empresa Ficticia de Prueba SpA')->count());
    }

    public function test_un_rut_con_digito_verificador_incorrecto_o_ya_registrado_se_rechaza(): void
    {
        $this->vendedor();
        $rut = Rut::desdeNumero(45_700_002);
        $dvMalo = $rut->dv === '1' ? '2' : '1';

        $this->post('/registro/empresa', $this->datosEmpresa(['rut' => "{$rut->numero}-{$dvMalo}"]))
            ->assertSessionHasErrors(['rut' => 'El RUT no es válido: revisa el número y el dígito verificador.']);
        $this->post('/registro/empresa', $this->datosEmpresa(['rut' => 'no-es-rut']))->assertSessionHasErrors('rut');
        $this->assertSame(0, Cliente::where('nombre_razon_social', 'Empresa Ficticia de Prueba SpA')->count());

        // Ya registrado, aunque se escriba con otro formato.
        Cliente::create([
            'id_tipo_cliente' => DB::table('tipo_cliente')->where('codigo', 'B2B')->value('id_tipo_cliente'),
            'id_lista_precio' => $this->publica->id_lista_precio,
            'rut' => $rut->normalizado(),
            'nombre_razon_social' => 'Empresa que ya existe',
            'estado_cliente' => 'prospecto',
        ]);

        $this->post('/registro/empresa', $this->datosEmpresa(['rut' => $rut->formateado()]))
            ->assertSessionHasErrors(['rut' => 'Ese RUT ya está registrado. Si tu empresa ya tiene cuenta, contacta a tu ejecutivo.']);
        $this->assertGuest();
        $this->assertSame(0, Usuario::where('email', 'compras@empresa-ficticia.example')->count());
    }

    public function test_rn02_una_empresa_recien_registrada_ve_el_catalogo_pero_no_puede_comprar(): void
    {
        $this->vendedor();
        $this->post('/registro/empresa', $this->datosEmpresa())->assertRedirect('/catalogo');
        $empresa = Usuario::where('email', 'compras@empresa-ficticia.example')->sole();

        // Ve el catálogo con precios de lista («Público general», en neto como toda empresa) y «Agregar» deshabilitado.
        $this->actingAs($empresa)->get('/catalogo')->assertOk()->assertSee('Queso de prueba')->assertSee('$30.000')
            ->assertSee('Empresa pendiente de validación')->assertSee('data-agregar="TST-REG" disabled', false);

        $this->actingAs($empresa)->post('/carrito/items', ['sku' => 'TST-REG', 'cantidad' => 10])
            ->assertSessionHasErrors(['sku' => 'Tu empresa aún está pendiente de validación: podrás comprar cuando un ejecutivo la valide.']);
        $this->actingAs($empresa)->post('/pedidos', $this->datosCheckout($empresa))->assertSessionHasErrors();
        $this->assertSame(0, Pedido::count());
    }

    public function test_sin_vendedores_activos_la_empresa_queda_sin_asignar_y_sin_oportunidad(): void
    {
        $this->post('/registro/empresa', $this->datosEmpresa())->assertRedirect('/catalogo');

        $cliente = Cliente::where('nombre_razon_social', 'Empresa Ficticia de Prueba SpA')->sole();
        $this->assertNull($cliente->id_vendedor);
        $this->assertSame(0, Oportunidad::count());
        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->get('/crm')->assertOk()->assertSee('Empresa Ficticia de Prueba SpA');
    }

    // --- Apoyo -------------------------------------------------------------------

    private function datosPersona(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Paula Prueba',
            'email' => 'paula.prueba@correo.example',
            'password' => 'Clave1234',
            'password_confirmation' => 'Clave1234',
            'calle' => 'Calle Inventada 742',
            'id_comuna' => Comuna::where('nombre', 'Temuco')->value('id_comuna'),
            'consentimiento' => '1',
        ], $extra);
    }

    private function datosEmpresa(array $extra = []): array
    {
        return array_merge([
            'rut' => Rut::desdeNumero(45_700_099)->formateado(),
            'razon_social' => 'Empresa Ficticia de Prueba SpA',
            'contacto_nombre' => 'Tomás Inventado',
            'contacto_cargo' => 'Jefe de compras',
            'contacto_telefono' => '+56 9 5555 0777',
            'email' => 'compras@empresa-ficticia.example',
            'password' => 'Clave1234',
            'password_confirmation' => 'Clave1234',
            'alias' => 'Casino central',
            'calle' => 'Avenida Ficticia 100',
            'id_comuna' => Comuna::where('nombre', 'Temuco')->value('id_comuna'),
            'ventana_inicio' => '07:00',
            'ventana_fin' => '11:00',
            'consentimiento' => '1',
        ], $extra);
    }
}
