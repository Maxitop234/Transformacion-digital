<?php

namespace App\Models;

use App\Models\Concerns\EnCartera;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `direccion_despacho`: Puntos de entrega de un cliente (B2B: varias sucursales).
 */
class DireccionDespacho extends Modelo
{
    use EnCartera;

    protected $table = 'direccion_despacho';

    protected $primaryKey = 'id_direccion';

    /** @var list<string> */
    protected $fillable = [
        'id_cliente',
        'id_comuna',
        'alias',
        'calle',
        'referencia',
        'lat',
        'long',
        'ventana_inicio',
        'ventana_fin',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'long' => 'float',
            'activa' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function comuna(): BelongsTo
    {
        return $this->belongsTo(Comuna::class, 'id_comuna', 'id_comuna');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'id_direccion', 'id_direccion');
    }
}
