<?php

namespace App\Models;

use App\Models\Concerns\TieneClaveCompuesta;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `detalle_lista_precio`: Precio de cada producto en cada lista (N:M lista–producto).
 */
class DetalleListaPrecio extends Modelo
{
    use TieneClaveCompuesta;

    protected $table = 'detalle_lista_precio';

    /** @var list<string> */
    protected $primaryKey = ['id_lista_precio', 'id_producto'];

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'id_lista_precio',
        'id_producto',
        'precio_unitario',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
        ];
    }

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'id_lista_precio', 'id_lista_precio');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}
