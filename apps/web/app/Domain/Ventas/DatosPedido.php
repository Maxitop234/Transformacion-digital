<?php

namespace App\Domain\Ventas;

/** Lo que el cliente elige en el checkout (API.md §1.3, `POST /pedidos`). */
final readonly class DatosPedido
{
    public function __construct(
        public int $idDireccion,
        public string $fechaEntrega,
        public ?string $ordenCompra = null,
        public ?string $observaciones = null,
    ) {}
}
