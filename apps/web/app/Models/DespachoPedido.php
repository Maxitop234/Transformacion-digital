<?php

namespace App\Models;

use App\Models\Concerns\TieneClaveCompuesta;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `despacho_pedido`: Pedidos transportados en un despacho y el resultado de cada entrega.
 */
class DespachoPedido extends Modelo
{
    use TieneClaveCompuesta;

    protected $table = 'despacho_pedido';

    /** @var list<string> */
    protected $primaryKey = ['id_despacho', 'id_pedido'];

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'id_despacho',
        'id_pedido',
        'orden_parada',
        'estado_entrega',
        'fecha_entrega',
        'motivo_falla',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'datetime',
        ];
    }

    public function despacho(): BelongsTo
    {
        return $this->belongsTo(Despacho::class, 'id_despacho', 'id_despacho');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }
}
