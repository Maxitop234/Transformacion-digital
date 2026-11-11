<?php

namespace App\Domain\Ventas;

use App\Models\Carrito;
use Carbon\CarbonInterface;

/**
 * RN-04: un carrito `activo` sin actividad por `negocio.carrito_abandono_horas`
 * (24 h) pasa a `abandonado`. La actividad es `actualizado_en`, que se mueve
 * con cada cambio de productos o cantidades.
 *
 * Se ejecuta con el planificador (`carritos:marcar-abandonados`, ver
 * routes/console.php). Conserva `actualizado_en` para no borrar la fecha de
 * la última actividad real.
 */
final class MarcarCarritosAbandonados
{
    /** @return int carritos marcados */
    public function __invoke(?CarbonInterface $ahora = null): int
    {
        $limite = ($ahora ?? now())->copy()->subHours(config('negocio.carrito_abandono_horas'));

        return Carrito::where('estado', 'activo')
            ->where('actualizado_en', '<=', $limite)
            ->toBase()
            ->update(['estado' => 'abandonado']);
    }
}
