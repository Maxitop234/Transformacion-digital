<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `alerta`: Alerta operativa generada por una lectura crítica y gestionada hasta su resolución.
 */
class Alerta extends Modelo
{
    protected $table = 'alerta';

    protected $primaryKey = 'id_alerta';

    /** @var list<string> */
    protected $fillable = [
        'id_telemetria',
        'id_usuario_resuelve',
        'tipo',
        'severidad',
        'estado',
        'accion_correctiva',
        'fecha_resolucion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_resolucion' => 'datetime',
        ];
    }

    public function telemetria(): BelongsTo
    {
        return $this->belongsTo(Telemetria::class, 'id_telemetria', 'id_telemetria');
    }

    public function usuarioResuelve(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_resuelve', 'id_usuario');
    }
}
