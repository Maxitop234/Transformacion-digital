<?php

namespace App\Domain\Catalogo;

use App\Models\Cliente;
use App\Models\ListaPrecio;

/**
 * Con qué lista y bajo qué perfil ve los precios quien navega el portal.
 * Lo resuelve `Precios::perfil()`.
 */
final readonly class PerfilPrecio
{
    public function __construct(
        public ListaPrecio $lista,
        public string $etiqueta,
        public bool $esB2B,
        /** Cuenta de cliente activa: puede comprar (RN-02). */
        public bool $puedeComprar,
        public ?Cliente $cliente,
    ) {}

    /** B2B ve precios netos; el resto, con IVA incluido. */
    public function muestraNeto(): bool
    {
        return $this->esB2B;
    }
}
