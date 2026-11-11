<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `pedido`: Cabecera del pedido; el cliente se obtiene desde la dirección de despacho (3FN).
 */
class Pedido extends Modelo
{
    protected $table = 'pedido';

    protected $primaryKey = 'id_pedido';

    /** @var list<string> */
    protected $fillable = [
        'id_direccion',
        'id_estado_pedido',
        'id_carrito',
        'id_oportunidad',
        'id_vendedor_registra',
        'canal',
        'fecha_pedido',
        'fecha_entrega_solicitada',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pedido' => 'datetime',
            'fecha_entrega_solicitada' => 'date',
        ];
    }

    public function direccionDespacho(): BelongsTo
    {
        return $this->belongsTo(DireccionDespacho::class, 'id_direccion', 'id_direccion');
    }

    public function estadoPedido(): BelongsTo
    {
        return $this->belongsTo(EstadoPedido::class, 'id_estado_pedido', 'id_estado_pedido');
    }

    public function carrito(): BelongsTo
    {
        return $this->belongsTo(Carrito::class, 'id_carrito', 'id_carrito');
    }

    public function oportunidad(): BelongsTo
    {
        return $this->belongsTo(Oportunidad::class, 'id_oportunidad', 'id_oportunidad');
    }

    public function vendedorRegistra(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'id_vendedor_registra', 'id_vendedor');
    }

    public function detallesPedido(): HasMany
    {
        return $this->hasMany(DetallePedido::class, 'id_pedido', 'id_pedido');
    }

    public function historialesEstadoPedido(): HasMany
    {
        return $this->hasMany(HistorialEstadoPedido::class, 'id_pedido', 'id_pedido');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'id_pedido', 'id_pedido');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'id_pedido', 'id_pedido');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'id_pedido', 'id_pedido');
    }

    public function despachosPedido(): HasMany
    {
        return $this->hasMany(DespachoPedido::class, 'id_pedido', 'id_pedido');
    }
}
