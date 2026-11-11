<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `rango_conservacion`: Rangos de temperatura de conservación; de aquí sale el umbral de ruptura de frío (RN-07).
 */
class RangoConservacion extends Modelo
{
    protected $table = 'rango_conservacion';

    protected $primaryKey = 'id_rango';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'temp_min_c',
        'temp_max_c',
    ];

    protected function casts(): array
    {
        return [
            'temp_min_c' => 'decimal:1',
            'temp_max_c' => 'decimal:1',
        ];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_rango', 'id_rango');
    }
}
