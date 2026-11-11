<?php

namespace App\Domain\Ventas;

/** Transición fuera del ciclo de vida de BUSINESS_RULES.md §4 (409). */
class TransicionInvalida extends ConflictoPedido
{
    public function __construct(public readonly string $origen, public readonly string $destino)
    {
        parent::__construct("El pedido está «{$origen}» y no puede pasar a «{$destino}».");
    }
}
