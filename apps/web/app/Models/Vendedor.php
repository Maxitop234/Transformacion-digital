<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `vendedor`: Ejecutivos comerciales que operan el CRM.
 */
class Vendedor extends Modelo
{
    protected $table = 'vendedor';

    protected $primaryKey = 'id_vendedor';

    /** @var list<string> */
    protected $fillable = [
        'id_usuario',
        'nombre',
        'telefono',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'id_vendedor', 'id_vendedor');
    }

    public function oportunidades(): HasMany
    {
        return $this->hasMany(Oportunidad::class, 'id_vendedor', 'id_vendedor');
    }

    public function interacciones(): HasMany
    {
        return $this->hasMany(Interaccion::class, 'id_vendedor', 'id_vendedor');
    }

    public function pedidosRegistrados(): HasMany
    {
        return $this->hasMany(Pedido::class, 'id_vendedor_registra', 'id_vendedor');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'id_vendedor', 'id_vendedor');
    }
}
