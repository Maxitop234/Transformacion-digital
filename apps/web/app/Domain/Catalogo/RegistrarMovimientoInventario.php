<?php

namespace App\Domain\Catalogo;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ingresos y ajustes manuales de stock (T-007). Todo cambio actualiza
 * `inventario` y deja su fila en `movimiento_inventario` con el responsable,
 * dentro de una misma transacción (DATA_MODEL.md §2).
 *
 * - `ingreso`: cantidad positiva, suma a `stock_disponible`.
 * - `ajuste`: cantidad con signo, corrige `stock_disponible`. No puede dejar
 *   el disponible bajo cero ni bajo lo reservado por pedidos (RN-04).
 */
final class RegistrarMovimientoInventario
{
    public const TIPOS_MANUALES = ['ingreso', 'ajuste'];

    public function __invoke(int $idProducto, string $tipo, string $cantidad, Usuario $responsable): MovimientoInventario
    {
        if (! in_array($tipo, self::TIPOS_MANUALES, true)) {
            throw ValidationException::withMessages(['tipo' => 'Solo se registran ingresos y ajustes.']);
        }

        $centesimas = self::centesimas($cantidad);

        if ($centesimas === 0) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad no puede ser cero.']);
        }

        if ($tipo === 'ingreso' && $centesimas < 0) {
            throw ValidationException::withMessages(['cantidad' => 'Un ingreso debe ser una cantidad positiva.']);
        }

        return DB::transaction(function () use ($idProducto, $tipo, $centesimas, $responsable) {
            $inventario = Inventario::whereKey($idProducto)->lockForUpdate()->first()
                ?? Inventario::create(['id_producto' => $idProducto, 'stock_disponible' => 0, 'stock_reservado' => 0]);

            $nuevoDisponible = self::centesimas((string) $inventario->stock_disponible) + $centesimas;

            if ($nuevoDisponible < self::centesimas((string) $inventario->stock_reservado)) {
                throw ValidationException::withMessages([
                    'cantidad' => sprintf(
                        'El ajuste dejaría %s disponibles, menos que lo reservado por pedidos (%s).',
                        self::decimal($nuevoDisponible),
                        $inventario->stock_reservado,
                    ),
                ]);
            }

            $inventario->stock_disponible = self::decimal($nuevoDisponible);
            $inventario->save();

            return MovimientoInventario::create([
                'id_producto' => $idProducto,
                'id_usuario' => $responsable->id_usuario,
                'tipo' => $tipo,
                'cantidad' => self::decimal($centesimas),
            ]);
        });
    }

    /**
     * Las cantidades son NUMERIC(12,2): se opera en centésimas enteras para
     * no arrastrar errores de coma flotante.
     */
    private static function centesimas(string $valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    private static function decimal(int $centesimas): string
    {
        return number_format($centesimas / 100, 2, '.', '');
    }
}
