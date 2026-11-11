<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `rol`: Perfiles de acceso (administrador, vendedor, despachador, cliente_b2b, cliente_b2c).
 */
class Rol extends Modelo
{
    public const ADMINISTRADOR = 'administrador';

    public const VENDEDOR = 'vendedor';

    public const DESPACHADOR = 'despachador';

    public const CLIENTE_B2B = 'cliente_b2b';

    public const CLIENTE_B2C = 'cliente_b2c';

    /** Roles que trabajan en el CRM (BUSINESS_RULES.md §1). */
    public const PERSONAL = [self::ADMINISTRADOR, self::VENDEDOR, self::DESPACHADOR];

    /** Roles que compran en el portal. */
    public const CLIENTES = [self::CLIENTE_B2B, self::CLIENTE_B2C];

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
