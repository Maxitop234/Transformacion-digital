<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `camion`: Flota de camiones frigoríficos; camion_id es el código que envía la telemetría.
 */
class Camion extends Modelo
{
    protected $table = 'camion';

    protected $primaryKey = 'camion_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'camion_id',
        'patente',
        'capacidad_kg',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function despachos(): HasMany
    {
        return $this->hasMany(Despacho::class, 'camion_id', 'camion_id');
    }

    public function telemetrias(): HasMany
    {
        return $this->hasMany(Telemetria::class, 'camion_id', 'camion_id');
    }
}
