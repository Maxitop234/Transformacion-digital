<?php

namespace App\Domain\Ventas;

use App\Models\Carrito;

/**
 * Carrito con precios y totales recalculados (lo arma `Carritos::resumen()`).
 * Montos en pesos enteros: neto, IVA (`negocio.iva_pct`) y total.
 */
final readonly class ResumenCarrito
{
    /**
     * @param  list<LineaCarrito>  $lineas
     */
    public function __construct(
        public ?Carrito $carrito,
        public array $lineas,
        public int $neto,
        public int $iva,
        public int $total,
    ) {}

    public function vacio(): bool
    {
        return $this->lineas === [];
    }

    public function cantidadProductos(): int
    {
        return count($this->lineas);
    }
}
