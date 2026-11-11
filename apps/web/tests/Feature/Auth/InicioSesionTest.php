<?php

namespace Tests\Feature\Auth;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreaUsuarios;
use Tests\TestCase;

class InicioSesionTest extends TestCase
{
    use CreaUsuarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
    }

    public function test_el_formulario_de_inicio_de_sesion_se_muestra(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión')->assertSee('name="_token"', false);
    }

    public function test_el_personal_entra_y_va_al_crm(): void
    {
        $vendedor = $this->vendedor();

        $this->post('/login', ['email' => $vendedor->email, 'password' => self::CLAVE])
            ->assertRedirect('/crm');

        $this->assertAuthenticatedAs($vendedor);
    }

    public function test_un_cliente_entra_y_va_al_portal(): void
    {
        $cliente = $this->usuario(Rol::CLIENTE_B2C);

        $this->post('/login', ['email' => $cliente->email, 'password' => self::CLAVE])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($cliente);
    }

    public function test_el_correo_no_distingue_mayusculas(): void
    {
        $admin = $this->usuario(Rol::ADMINISTRADOR);

        $this->post('/login', ['email' => strtoupper($admin->email), 'password' => self::CLAVE])->assertRedirect('/crm');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_iniciar_sesion_registra_el_ultimo_acceso(): void
    {
        $admin = $this->usuario(Rol::ADMINISTRADOR);
        $this->assertNull($admin->ultimo_acceso);

        $this->post('/login', ['email' => $admin->email, 'password' => self::CLAVE]);

        $this->assertNotNull($admin->refresh()->ultimo_acceso);
        $this->assertTrue($admin->ultimo_acceso->isToday());
    }

    public function test_vuelve_a_la_pagina_pedida_si_su_rol_la_permite(): void
    {
        $vendedor = $this->vendedor();

        $this->get('/crm/clientes')->assertRedirect('/login');
        $this->post('/login', ['email' => $vendedor->email, 'password' => self::CLAVE])->assertRedirect('/crm/clientes');
    }

    public function test_un_cliente_que_pidio_el_crm_entra_al_portal_y_no_a_un_403(): void
    {
        $cliente = $this->usuario(Rol::CLIENTE_B2C);

        $this->get('/crm')->assertRedirect('/login');
        $this->post('/login', ['email' => $cliente->email, 'password' => self::CLAVE])->assertRedirect('/');
    }

    public function test_contrasena_incorrecta_no_entra(): void
    {
        $admin = $this->usuario(Rol::ADMINISTRADOR);

        $this->from('/login')
            ->post('/login', ['email' => $admin->email, 'password' => 'otra-clave'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_un_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $inactivo = $this->usuario(Rol::VENDEDOR, activo: false);

        $this->post('/login', ['email' => $inactivo->email, 'password' => self::CLAVE])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
        $this->assertNull($inactivo->refresh()->ultimo_acceso);
    }

    public function test_desactivar_un_usuario_cierra_su_sesion_abierta(): void
    {
        $vendedor = $this->vendedor();
        $this->post('/login', ['email' => $vendedor->email, 'password' => self::CLAVE]);
        $this->get('/crm')->assertOk();

        // Un administrador lo desactiva mientras la sesión sigue abierta.
        Usuario::whereKey($vendedor->id_usuario)->update(['activo' => false]);
        // Petición nueva: el guard vuelve a leer al usuario desde la sesión y la base.
        $this->app['auth']->forgetGuards();

        $this->get('/crm')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_limita_los_intentos_de_inicio_de_sesion(): void
    {
        $admin = $this->usuario(Rol::ADMINISTRADOR);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $admin->email, 'password' => 'mala-'.$i]);
        }

        // Al sexto intento queda bloqueado aunque la contraseña sea correcta.
        $this->post('/login', ['email' => $admin->email, 'password' => self::CLAVE])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('email'));
    }

    public function test_cerrar_sesion(): void
    {
        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_las_contrasenas_se_guardan_con_hash(): void
    {
        $usuario = $this->usuario(Rol::CLIENTE_B2B);
        $guardada = Usuario::whereKey($usuario->id_usuario)->value('password_hash');

        $this->assertNotSame(self::CLAVE, $guardada);
        $this->assertTrue(Hash::isHashed($guardada));
        $this->assertTrue(Hash::check(self::CLAVE, $guardada));
    }

    public function test_quien_ya_inicio_sesion_no_ve_el_formulario(): void
    {
        $this->actingAs($this->vendedor())->get('/login')->assertRedirect('/crm');
    }
}
