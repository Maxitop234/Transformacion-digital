<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tabla `producto`: Productos refrigerados y congelados que se venden y despachan.
 */
class Producto extends Modelo
{
    protected $table = 'producto';

    protected $primaryKey = 'id_producto';

    /** @var list<string> */
    protected $fillable = [
        'id_categoria',
        'id_rango',
        'sku',
        'nombre',
        'descripcion',
        'unidad_medida',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function rangoConservacion(): BelongsTo
    {
        return $this->belongsTo(RangoConservacion::class, 'id_rango', 'id_rango');
    }

    public function detallesListaPrecio(): HasMany
    {
        return $this->hasMany(DetalleListaPrecio::class, 'id_producto', 'id_producto');
    }

    public function inventario(): HasOne
    {
        return $this->hasOne(Inventario::class, 'id_producto', 'id_producto');
    }

    public function itemsCarrito(): HasMany
    {
        return $this->hasMany(ItemCarrito::class, 'id_producto', 'id_producto');
    }

    public function detallesPedido(): HasMany
    {
        return $this->hasMany(DetallePedido::class, 'id_producto', 'id_producto');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'id_producto', 'id_producto');
    }

    /** Disponible para venta = stock_disponible − stock_reservado (RN-04). */
    public function disponibleParaVenta(): float
    {
        if ($this->inventario === null) {
            return 0.0;
        }

        return max(0.0, round((float) $this->inventario->stock_disponible - (float) $this->inventario->stock_reservado, 2));
    }
}
