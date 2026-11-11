<?php

namespace App\Domain\Catalogo;

use App\Domain\Ventas\Montos;
use App\Domain\Ventas\SinStock;
use App\Models\DetallePedido;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

/**
 * Reserva y liberación de stock de un pedido (RN-04). Debe llamarse dentro
 * de la transacción que crea o cancela el pedido.
 *
 * La reserva es un UPDATE condicional (`stock_disponible − stock_reservado
 * >= cantidad`): si dos compras piden el último stock a la vez, PostgreSQL
 * hace esperar a la segunda sobre la fila de `inventario`, vuelve a evaluar
 * la condición con el valor ya confirmado y no actualiza nada, así que no
 * hay sobreventa. Los productos se recorren por id para que dos pedidos con
 * los mismos productos bloqueen en el mismo orden y no se crucen.
 *
 * Cada cambio deja su fila en `movimiento_inventario`: `reserva` suma y
 * `liberacion` resta a `stock_reservado` (DATA_MODEL.md §2).
 */
final class ReservasStock
{
    /**
     * @param  array<int, int>  $centesimasPorProducto  id_producto => cantidad en centésimas
     *
     * @throws SinStock
     */
    public function reservar(Pedido $pedido, array $centesimasPorProducto, int $idUsuario): void
    {
        ksort($centesimasPorProducto);

        foreach ($centesimasPorProducto as $idProducto => $centesimas) {
            $cantidad = Montos::decimal($centesimas);

            $reservadas = DB::update(
                'UPDATE inventario
                    SET stock_reservado = stock_reservado + ?, actualizado_en = now()
                  WHERE id_producto = ? AND stock_disponible - stock_reservado >= ?',
                [$cantidad, $idProducto, $cantidad],
            );

            if ($reservadas === 0) {
                throw new SinStock(Producto::whereKey($idProducto)->value('nombre') ?? "el producto {$idProducto}");
            }

            $this->movimiento($pedido, $idProducto, 'reserva', $cantidad, $idUsuario);
        }
    }

    /** Devuelve a disponible lo que el pedido tenía reservado (cancelación). */
    public function liberar(Pedido $pedido, int $idUsuario): void
    {
        $detalles = DetallePedido::where('id_pedido', $pedido->id_pedido)->orderBy('id_producto')->get();

        foreach ($detalles as $detalle) {
            $cantidad = (string) $detalle->cantidad;

            DB::update(
                'UPDATE inventario
                    SET stock_reservado = stock_reservado - ?, actualizado_en = now()
                  WHERE id_producto = ?',
                [$cantidad, $detalle->id_producto],
            );

            $this->movimiento($pedido, $detalle->id_producto, 'liberacion', '-'.$cantidad, $idUsuario);
        }
    }

    private function movimiento(Pedido $pedido, int $idProducto, string $tipo, string $cantidad, int $idUsuario): void
    {
        MovimientoInventario::create([
            'id_producto' => $idProducto,
            'id_pedido' => $pedido->id_pedido,
            'id_usuario' => $idUsuario,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
        ]);
    }
}
