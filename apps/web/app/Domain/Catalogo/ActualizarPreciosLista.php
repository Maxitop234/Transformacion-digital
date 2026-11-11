<?php

namespace App\Domain\Catalogo;

use App\Models\DetalleListaPrecio;
use App\Models\ListaPrecio;
use Illuminate\Support\Facades\DB;

/**
 * Fija el precio de cada producto en una lista (`detalle_lista_precio`).
 * Un precio vacío quita el producto de la lista.
 *
 * Solo toca la lista: los pedidos guardan su `precio_unitario_pactado`, así
 * que cambiar un precio no altera pedidos existentes (RN-01).
 */
final class ActualizarPreciosLista
{
    /** @param array<int|string, string|null> $precios id_producto => precio */
    public function __invoke(ListaPrecio $lista, array $precios): void
    {
        DB::transaction(function () use ($lista, $precios) {
            foreach ($precios as $idProducto => $precio) {
                $clave = ['id_lista_precio' => $lista->id_lista_precio, 'id_producto' => (int) $idProducto];

                if ($precio === null || $precio === '') {
                    DetalleListaPrecio::where($clave)->delete();

                    continue;
                }

                $existe = DetalleListaPrecio::where($clave)->exists();
                $existe
                    ? DetalleListaPrecio::where($clave)->update(['precio_unitario' => $precio])
                    : DetalleListaPrecio::create($clave + ['precio_unitario' => $precio]);
            }
        });
    }
}
