<?php

namespace App\Models;

use App\Models\Concerns\EnCartera;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `ticket`: Requerimientos y reclamos de clientes; la IA los clasifica (T-021).
 */
class Ticket extends Modelo
{
    use EnCartera;

    protected $table = 'ticket';

    protected $primaryKey = 'id_ticket';

    // Valores permitidos (DATA_MODEL.md `ticket`, CHECK en la base).
    public const SIN_CLASIFICAR = 'sin_clasificar';

    /** Categorías que se asignan al clasificar (sin `sin_clasificar`). */
    public const CATEGORIAS = ['consulta', 'reclamo', 'cambio_pedido', 'cotizacion', 'otro'];

    public const PRIORIDADES = ['baja', 'media', 'alta'];

    public const ESTADOS = ['abierto', 'en_proceso', 'resuelto', 'cerrado'];

    public const CERRADO = 'cerrado';

    /** @var list<string> */
    protected $fillable = [
        'id_cliente',
        'id_pedido',
        'id_vendedor',
        'asunto',
        'descripcion',
        'categoria',
        'prioridad',
        'estado',
        'clasificado_por_ia',
        'creado_en',
        'cerrado_en',
    ];

    protected function casts(): array
    {
        return [
            'clasificado_por_ia' => 'boolean',
            'creado_en' => 'datetime',
            'cerrado_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'id_vendedor', 'id_vendedor');
    }
}
