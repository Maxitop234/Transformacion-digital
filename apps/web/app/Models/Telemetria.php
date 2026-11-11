<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `telemetria`: Lecturas periódicas de cada camión, insertadas por el simulador IoT. Sin particionar (ADR-0017). Escrita por el simulador IoT con `iot_writer`; la aplicación solo la lee.
 */
class Telemetria extends Modelo
{
    protected $table = 'telemetria';

    protected $primaryKey = 'id_telemetria';

    /** @var list<string> */
    protected $fillable = [
        'camion_id',
        'fecha_hora',
        'temp_camara',
        'lat',
        'long',
        'combustible_pct',
        'alerta_ruptura_frio',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'temp_camara' => 'float',
            'lat' => 'float',
            'long' => 'float',
            'alerta_ruptura_frio' => 'boolean',
        ];
    }

    public function camion(): BelongsTo
    {
        return $this->belongsTo(Camion::class, 'camion_id', 'camion_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class, 'id_telemetria', 'id_telemetria');
    }
}
