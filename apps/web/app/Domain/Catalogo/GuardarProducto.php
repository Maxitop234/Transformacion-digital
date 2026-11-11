<?php

namespace App\Domain\Catalogo;

use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

/**
 * Alta y edición de productos. Un producto nuevo nace con su fila de
 * `inventario` en cero (relación 1:1): el stock se carga después con un
 * ingreso, que deja su movimiento.
 */
final class GuardarProducto
{
    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Producto
    {
        return DB::transaction(function () use ($datos) {
            $producto = Producto::create($datos);
            Inventario::create(['id_producto' => $producto->id_producto, 'stock_disponible' => 0, 'stock_reservado' => 0]);

            return $producto;
        });
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(Producto $producto, array $datos): Producto
    {
        $producto->update($datos);

        return $producto;
    }
}
