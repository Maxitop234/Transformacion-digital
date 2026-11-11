<?php

namespace App\Models;

use App\Models\Concerns\TieneClaveCompuesta;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `item_carrito`: Productos dentro de un carrito; el precio se calcula con la lista del cliente.
 */
class ItemCarrito extends Modelo
{
    use TieneClaveCompuesta;

    protected $table = 'item_carrito';

    /** @var list<string> */
    protected $primaryKey = ['id_carrito', 'id_producto'];

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'id_carrito',
        'id_producto',
        'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
        ];
    }

    public function carrito(): BelongsTo
    {
        return $this->belongsTo(Carrito::class, 'id_carrito', 'id_carrito');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}
