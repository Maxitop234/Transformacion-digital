<?php

namespace App\Domain\Logistica;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** La operación no corresponde al estado del despacho, la parada o la alerta (409). */
class ConflictoLogistica extends HttpException
{
    public function __construct(string $mensaje)
    {
        parent::__construct(409, $mensaje);
    }
}
