<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `estado_pedido`: Catálogo de estados del pedido.
 */
class EstadoPedido extends Modelo
{
    protected $table = 'estado_pedido';

    protected $primaryKey = 'id_estado_pedido';

    // Nombres del catálogo (DATA_MODEL.md §3). Las transiciones válidas viven en
    // App\Domain\Ventas\TransicionesPedido.
    public const PENDIENTE = 'Pendiente';

    public const CONFIRMADO = 'Confirmado';

    public const EN_PREPARACION = 'En preparación';

    public const EN_RUTA = 'En ruta';

    public const ENTREGADO = 'Entregado';

    public const RECHAZADO = 'Rechazado';

    public const CANCELADO = 'Cancelado';

    public static function idDe(string $nombre): int
    {
        return static::where('nombre', $nombre)->valueOrFail('id_estado_pedido');
    }

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'orden',
        'es_final',
    ];

    protected function casts(): array
    {
        return [
            'es_final' => 'boolean',
        ];
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'id_estado_pedido', 'id_estado_pedido');
    }

    public function historialesEstadoPedido(): HasMany
    {
        return $this->hasMany(HistorialEstadoPedido::class, 'id_estado_pedido', 'id_estado_pedido');
    }
}
