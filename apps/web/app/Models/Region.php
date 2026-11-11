<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `region`: Regiones de Chile donde Cofrial despacha.
 */
class Region extends Modelo
{
    protected $table = 'region';

    protected $primaryKey = 'id_region';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
    ];

    public function comunas(): HasMany
    {
        return $this->hasMany(Comuna::class, 'id_region', 'id_region');
    }
}
