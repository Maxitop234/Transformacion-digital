<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `etapa_pipeline`: Etapas del pipeline comercial.
 */
class EtapaPipeline extends Modelo
{
    protected $table = 'etapa_pipeline';

    protected $primaryKey = 'id_etapa';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'orden',
        'es_cierre',
        'es_ganada',
    ];

    protected function casts(): array
    {
        return [
            'es_cierre' => 'boolean',
            'es_ganada' => 'boolean',
        ];
    }

    public function oportunidades(): HasMany
    {
        return $this->hasMany(Oportunidad::class, 'id_etapa', 'id_etapa');
    }

    public function historialesEtapa(): HasMany
    {
        return $this->hasMany(HistorialEtapa::class, 'id_etapa', 'id_etapa');
    }
}
