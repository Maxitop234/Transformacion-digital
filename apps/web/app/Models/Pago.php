<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `pago`: Pagos asociados a un pedido (RN-05).
 */
class Pago extends Modelo
{
    protected $table = 'pago';

    protected $primaryKey = 'id_pago';

    /** @var list<string> */
    protected $fillable = [
        'id_pedido',
        'monto',
        'metodo',
        'estado',
        'referencia_externa',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_hora' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }
}
