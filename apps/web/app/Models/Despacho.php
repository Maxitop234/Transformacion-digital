<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `despacho`: Viaje de un camión con un conductor; agrupa varios pedidos en una ruta.
 */
class Despacho extends Modelo
{
    protected $table = 'despacho';

    protected $primaryKey = 'id_despacho';

    /** @var list<string> */
    protected $fillable = [
        'camion_id',
        'id_conductor',
        'estado',
        'fecha_programada',
        'salida_real',
        'llegada_real',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada' => 'date',
            'salida_real' => 'datetime',
            'llegada_real' => 'datetime',
        ];
    }

    public function camion(): BelongsTo
    {
        return $this->belongsTo(Camion::class, 'camion_id', 'camion_id');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class, 'id_conductor', 'id_conductor');
    }

    public function despachosPedido(): HasMany
    {
        return $this->hasMany(DespachoPedido::class, 'id_despacho', 'id_despacho');
    }
}
