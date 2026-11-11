<?php

namespace App\Models;

use App\Models\Concerns\SoloInsercion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `movimiento_inventario`: Bitácora de cada cambio de stock (solo inserción).
 */
class MovimientoInventario extends Modelo
{
    use SoloInsercion;

    protected $table = 'movimiento_inventario';

    protected $primaryKey = 'id_movimiento';

    /** @var list<string> */
    protected $fillable = [
        'id_producto',
        'id_pedido',
        'id_usuario',
        'tipo',
        'cantidad',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'fecha_hora' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
