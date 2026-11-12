<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Detrás del balanceador de AWS el HTTPS termina en el ALB (T-024): la
 * aplicación debe reconocer X-Forwarded-Proto para no generar enlaces http.
 */
class BalanceadorTest extends TestCase
{
    public function test_detras_del_balanceador_genera_enlaces_https(): void
    {
        $respuesta = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-For' => '203.0.113.7',
        ])->get('/login');

        $respuesta->assertOk();
        $respuesta->assertSee('action="https://', false);
    }

    public function test_sin_balanceador_conserva_el_esquema_de_la_peticion(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('action="https://', false);
    }
}
