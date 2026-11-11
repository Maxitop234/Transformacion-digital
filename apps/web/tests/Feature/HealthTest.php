<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_responde_ok_con_la_base_disponible(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_health_responde_503_sin_conexion_a_la_base(): void
    {
        $conexion = config('database.default');
        config(["database.connections.{$conexion}.database" => '/ruta/inexistente/cofrial.sqlite']);
        config(["database.connections.{$conexion}.host" => '203.0.113.1']);
        DB::purge($conexion);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable']);
    }

    public function test_health_no_abre_sesion(): void
    {
        $this->get('/health')->assertCookieMissing(config('session.cookie'));
    }
}
