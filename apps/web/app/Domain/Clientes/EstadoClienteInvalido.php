<?php

namespace App\Domain\Clientes;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** La acción no corresponde al estado actual del cliente (409, API.md §4). */
class EstadoClienteInvalido extends HttpException
{
    public function __construct(string $mensaje)
    {
        parent::__construct(409, $mensaje);
    }
}
