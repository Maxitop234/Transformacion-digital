<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `lista_precio`: Listas de precios: una pública (B2C) y una o más institucionales (B2B).
 */
class ListaPrecio extends Modelo
{
    protected $table = 'lista_precio';

    protected $primaryKey = 'id_lista_precio';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'vigente_desde',
        'vigente_hasta',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
            'activa' => 'boolean',
        ];
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'id_lista_precio', 'id_lista_precio');
    }

    public function detallesListaPrecio(): HasMany
    {
        return $this->hasMany(DetalleListaPrecio::class, 'id_lista_precio', 'id_lista_precio');
    }
}
