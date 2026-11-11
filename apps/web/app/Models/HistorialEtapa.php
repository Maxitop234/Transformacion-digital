<?php

namespace App\Models;

use App\Models\Concerns\SoloInsercion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `historial_etapa`: Cada cambio de etapa de una oportunidad (solo inserción).
 */
class HistorialEtapa extends Modelo
{
    use SoloInsercion;

    protected $table = 'historial_etapa';

    protected $primaryKey = 'id_historial';

    /** @var list<string> */
    protected $fillable = [
        'id_oportunidad',
        'id_etapa',
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

    public function oportunidad(): BelongsTo
    {
        return $this->belongsTo(Oportunidad::class, 'id_oportunidad', 'id_oportunidad');
    }

    public function etapaPipeline(): BelongsTo
    {
        return $this->belongsTo(EtapaPipeline::class, 'id_etapa', 'id_etapa');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
