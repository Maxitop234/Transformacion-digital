<?php

namespace Tests\Unit;

use App\Domain\Clientes\Rut;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RutTest extends TestCase
{
    public function test_calcula_el_digito_verificador(): void
    {
        $this->assertSame('5', Rut::calcularDv(12345678));
        $this->assertSame('1', Rut::calcularDv(11111111));
        $this->assertSame('K', Rut::calcularDv(10000013));
        $this->assertSame('0', Rut::calcularDv(10000004));
    }

    public function test_valida_y_normaliza_distintos_formatos(): void
    {
        $this->assertSame('12345678-5', Rut::desdeTexto('12.345.678-5')->normalizado());
        $this->assertSame('10000013-K', Rut::desdeTexto('10000013k')->normalizado());
        $this->assertSame('12.345.678-5', Rut::desdeTexto('123456785')->formateado());
        $this->assertTrue(Rut::esValido('11.111.111-1'));
    }

    public function test_rechaza_digito_verificador_incorrecto_o_formato_invalido(): void
    {
        $this->assertFalse(Rut::esValido('12.345.678-9'));
        $this->assertFalse(Rut::esValido('abc'));
        $this->assertFalse(Rut::esValido(''));

        $this->expectException(InvalidArgumentException::class);
        Rut::desdeTexto('12345678-0');
    }
}
