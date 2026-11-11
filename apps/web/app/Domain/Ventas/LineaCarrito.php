<?php

namespace App\Domain\Ventas;

use App\Models\Producto;

/** Una línea del carrito con el precio vigente de la lista del cliente. */
final readonly class LineaCarrito
{
    public function __construct(
        public Producto $producto,
        /** NUMERIC(12,2) como texto. */
        public string $cantidad,
        /** Precio neto unitario de la lista; null si la lista ya no lo incluye o el producto se desactivó. */
        public ?string $precioNeto,
        /** Precio × cantidad al peso; null sin precio (no entra en los totales). */
        public ?int $subtotalNeto,
        /** Disponible para venta en este momento (RN-04). */
        public float $disponible,
    ) {}

    public function tienePrecio(): bool
    {
        return $this->precioNeto !== null;
    }

    /** La cantidad pedida ya no está disponible (otro pedido reservó stock). */
    public function excedeDisponible(): bool
    {
        return (float) $this->cantidad > $this->disponible;
    }
}
