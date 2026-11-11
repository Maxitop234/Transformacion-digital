<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `rol`: Perfiles de acceso (administrador, vendedor, despachador, cliente_b2b, cliente_b2c).
 */
class Rol extends Modelo
{
    protected $table = 'rol';

    protected $primaryKey = 'id_rol';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'id_rol', 'id_rol');
    }
}
