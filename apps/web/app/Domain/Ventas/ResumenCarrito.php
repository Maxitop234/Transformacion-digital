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

    /** Mensaje de RN-07 si el carrito mezcla rangos de frío; null si no. */
    public function rangosMixtos(): ?string
    {
        return CrearPedido::mensajeRangosMixtos(array_map(fn (LineaCarrito $l) => $l->producto->rangoConservacion?->nombre, $this->lineas));
    }

    public function cantidadProductos(): int
    {
        return count($this->lineas);
    }
}
