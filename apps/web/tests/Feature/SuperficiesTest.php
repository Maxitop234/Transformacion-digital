<?php

namespace Tests\Feature;

use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreaUsuarios;
use Tests\TestCase;

class SuperficiesTest extends TestCase
{
    use CreaUsuarios;
    use RefreshDatabase;

    public function test_portal_usa_su_layout(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-superficie="portal"', false)
            ->assertSee('<html lang="es">', false);
    }

    public function test_crm_usa_su_layout(): void
    {
        $this->sembrarCatalogos();

        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))
            ->get('/crm')
            ->assertOk()
            ->assertSee('data-superficie="crm"', false);
    }
}
