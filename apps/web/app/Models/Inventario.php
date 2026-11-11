<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `inventario`: Existencias actuales por producto (una sola bodega). Disponible para venta = stock_disponible - stock_reservado (RN-04).
 */
class Inventario extends Modelo
{
    protected $table = 'inventario';

    protected $primaryKey = 'id_producto';

    public $incrementing = false;

    public $timestamps = true;

    const CREATED_AT = null;

    const UPDATED_AT = 'actualizado_en';

    /** @var list<string> */
    protected $fillable = [
        'id_producto',
        'stock_disponible',
        'stock_reservado',
        'actualizado_en',
    ];

    protected function casts(): array
    {
        return [
            'stock_disponible' => 'decimal:2',
            'stock_reservado' => 'decimal:2',
            'actualizado_en' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}
