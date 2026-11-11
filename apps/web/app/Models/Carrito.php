<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tabla `carrito`: Carrito de compras del portal.
 */
class Carrito extends Modelo
{
    protected $table = 'carrito';

    protected $primaryKey = 'id_carrito';

    public $timestamps = true;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'actualizado_en';

    /** @var list<string> */
    protected $fillable = [
        'id_cliente',
        'estado',
        'creado_en',
        'actualizado_en',
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function itemsCarrito(): HasMany
    {
        return $this->hasMany(ItemCarrito::class, 'id_carrito', 'id_carrito');
    }

    public function pedido(): HasOne
    {
        return $this->hasOne(Pedido::class, 'id_carrito', 'id_carrito');
    }
}
