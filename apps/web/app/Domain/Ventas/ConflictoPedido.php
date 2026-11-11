<?php

namespace App\Domain\Ventas;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Conflicto de negocio del pedido (API.md §4): sin stock, transición de
 * estado inválida o plazo de pago vencido. Se responde con 409 y el mensaje,
 * que es seguro de mostrar al usuario.
 */
class ConflictoPedido extends HttpException
{
    public function __construct(string $mensaje)
    {
        parent::__construct(409, $mensaje);
    }
}
