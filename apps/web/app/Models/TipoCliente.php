<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `tipo_cliente`: Perfiles comerciales: B2B institucional y B2C público general.
 */
class TipoCliente extends Modelo
{
    protected $table = 'tipo_cliente';

    protected $primaryKey = 'id_tipo_cliente';

    /** @var list<string> */
    protected $fillable = [
        'codigo',
        'nombre',
    ];

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'id_tipo_cliente', 'id_tipo_cliente');
    }
}
