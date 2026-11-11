<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `categoria`: Familias de productos (cárnicos, lácteos, congelados, etc.).
 */
class Categoria extends Modelo
{
    protected $table = 'categoria';

    protected $primaryKey = 'id_categoria';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_categoria', 'id_categoria');
    }
}
