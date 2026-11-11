<?php

namespace Tests\Feature;

use Tests\TestCase;

class SuperficiesTest extends TestCase
{
    public function test_portal_usa_su_layout(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-superficie="portal"', false)
            ->assertSee('<html lang="es">', false);
    }

    public function test_crm_usa_su_layout(): void
    {
        $this->get('/crm')
            ->assertOk()
            ->assertSee('data-superficie="crm"', false);
    }
}
