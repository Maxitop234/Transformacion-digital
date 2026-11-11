<?php

namespace App\Domain\Ventas;

/** No queda disponible para venta suficiente para reservar (RN-04, 409). */
class SinStock extends ConflictoPedido
{
    public function __construct(public readonly string $producto)
    {
        parent::__construct("No queda stock suficiente de {$producto}. Revisa tu carrito.");
    }
}
