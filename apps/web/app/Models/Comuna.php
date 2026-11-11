<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `comuna`: Comunas de despacho, separadas de la región (3FN).
 */
class Comuna extends Modelo
{
    protected $table = 'comuna';

    protected $primaryKey = 'id_comuna';

    /** @var list<string> */
    protected $fillable = [
        'id_region',
        'nombre',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'id_region', 'id_region');
    }

    public function direccionesDespacho(): HasMany
    {
        return $this->hasMany(DireccionDespacho::class, 'id_comuna', 'id_comuna');
    }
}
