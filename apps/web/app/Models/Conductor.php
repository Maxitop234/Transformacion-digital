<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `conductor`: Conductores de la flota (datos personales: nunca se muestran al cliente).
 */
class Conductor extends Modelo
{
    protected $table = 'conductor';

    protected $primaryKey = 'id_conductor';

    /** @var list<string> */
    protected $fillable = [
        'rut',
        'nombre',
        'telefono',
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
        return $this->hasMany(Despacho::class, 'id_conductor', 'id_conductor');
    }
}
