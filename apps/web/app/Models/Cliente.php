<?php

namespace App\Models;

use App\Models\Concerns\EnCartera;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `cliente`: Cliente B2B/B2C o prospecto: entidad central que une ventas, CRM y despacho.
 */
class Cliente extends Modelo
{
    use EnCartera;

    protected $table = 'cliente';

    protected $primaryKey = 'id_cliente';

    /** @var list<string> */
    protected $fillable = [
        'id_usuario',
        'id_tipo_cliente',
        'id_lista_precio',
        'id_vendedor',
        'rut',
        'nombre_razon_social',
        'estado_cliente',
        'fecha_registro',
        'fecha_validacion',
        'consentimiento_datos_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_registro' => 'datetime',
            'fecha_validacion' => 'datetime',
            'consentimiento_datos_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function tipoCliente(): BelongsTo
    {
        return $this->belongsTo(TipoCliente::class, 'id_tipo_cliente', 'id_tipo_cliente');
    }

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'id_lista_precio', 'id_lista_precio');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'id_vendedor', 'id_vendedor');
    }

    public function contactosCliente(): HasMany
    {
        return $this->hasMany(ContactoCliente::class, 'id_cliente', 'id_cliente');
    }

    public function direccionesDespacho(): HasMany
    {
        return $this->hasMany(DireccionDespacho::class, 'id_cliente', 'id_cliente');
    }

    public function carritos(): HasMany
    {
        return $this->hasMany(Carrito::class, 'id_cliente', 'id_cliente');
    }

    public function oportunidades(): HasMany
    {
        return $this->hasMany(Oportunidad::class, 'id_cliente', 'id_cliente');
    }

    public function interacciones(): HasMany
    {
        return $this->hasMany(Interaccion::class, 'id_cliente', 'id_cliente');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'id_cliente', 'id_cliente');
    }
}
