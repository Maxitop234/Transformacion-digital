<?php

namespace App\Models;

use App\Models\Concerns\SoloInsercion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `historial_estado_pedido`: Bitácora de cada cambio de estado del pedido (solo inserción).
 */
class HistorialEstadoPedido extends Modelo
{
    use SoloInsercion;

    protected $table = 'historial_estado_pedido';

    protected $primaryKey = 'id_historial';

    /** @var list<string> */
    protected $fillable = [
        'id_pedido',
        'id_estado_pedido',
        'id_usuario',
        'fecha_hora',
        'comentario',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }

    public function estadoPedido(): BelongsTo
    {
        return $this->belongsTo(EstadoPedido::class, 'id_estado_pedido', 'id_estado_pedido');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
