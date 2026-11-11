<?php

namespace App\Models;

use App\Models\Concerns\TieneClaveCompuesta;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `detalle_pedido`: Líneas del pedido con el precio pactado al comprar (RN-01).
 */
class DetallePedido extends Modelo
{
    use TieneClaveCompuesta;

    protected $table = 'detalle_pedido';

    /** @var list<string> */
    protected $primaryKey = ['id_pedido', 'id_producto'];

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'id_pedido',
        'id_producto',
        'cantidad',
        'precio_unitario_pactado',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_unitario_pactado' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}
