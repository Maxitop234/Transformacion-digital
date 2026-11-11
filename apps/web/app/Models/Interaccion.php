<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `interaccion`: Toda comunicación con un cliente queda registrada aquí.
 */
class Interaccion extends Modelo
{
    protected $table = 'interaccion';

    protected $primaryKey = 'id_interaccion';

    /** @var list<string> */
    protected $fillable = [
        'id_cliente',
        'id_vendedor',
        'id_oportunidad',
        'tipo',
        'resumen',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
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

    public function oportunidad(): BelongsTo
    {
        return $this->belongsTo(Oportunidad::class, 'id_oportunidad', 'id_oportunidad');
    }
}
