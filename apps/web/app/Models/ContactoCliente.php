<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla `contacto_cliente`: Personas de contacto de un cliente (un B2B puede tener varias).
 */
class ContactoCliente extends Modelo
{
    protected $table = 'contacto_cliente';

    protected $primaryKey = 'id_contacto';

    /** @var list<string> */
    protected $fillable = [
        'id_cliente',
        'nombre',
        'cargo',
        'email',
        'telefono',
        'es_principal',
    ];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }
}
