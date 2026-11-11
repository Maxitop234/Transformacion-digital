<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `oportunidad`: Oportunidad de venta de un cliente o prospecto dentro del pipeline.
 */
class Oportunidad extends Modelo
{
    protected $table = 'oportunidad';

    protected $primaryKey = 'id_oportunidad';

    /** @var list<string> */
    protected $fillable = [
        'id_cliente',
        'id_vendedor',
        'id_etapa',
        'titulo',
        'origen',
        'monto_estimado',
        'fecha_creacion',
        'fecha_cierre_estimada',
        'fecha_cierre',
        'motivo_perdida',
    ];

    protected function casts(): array
    {
        return [
            'monto_estimado' => 'decimal:2',
            'fecha_creacion' => 'datetime',
            'fecha_cierre_estimada' => 'date',
            'fecha_cierre' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'id_vendedor', 'id_vendedor');
    }

    public function etapaPipeline(): BelongsTo
    {
        return $this->belongsTo(EtapaPipeline::class, 'id_etapa', 'id_etapa');
    }

    public function historialesEtapa(): HasMany
    {
        return $this->hasMany(HistorialEtapa::class, 'id_oportunidad', 'id_oportunidad');
    }

    public function interacciones(): HasMany
    {
        return $this->hasMany(Interaccion::class, 'id_oportunidad', 'id_oportunidad');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'id_oportunidad', 'id_oportunidad');
    }
}
