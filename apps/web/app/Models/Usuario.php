<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Tabla `usuario`: Credenciales de acceso al portal y al CRM (un login para todos los perfiles).
 */
class Usuario extends Authenticatable
{
    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /** Columna de la contraseña con hash (DATA_MODEL.md). */
    protected $authPasswordName = 'password_hash';

    /** El modelo no tiene columna «recordarme». */
    protected $rememberTokenName = '';

    /** @var list<string> */
    protected $fillable = [
        'id_rol',
        'email',
        'password_hash',
        'activo',
        'ultimo_acceso',
        'creado_en',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultimo_acceso' => 'datetime',
            'creado_en' => 'datetime',
            'password_hash' => 'hashed',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function vendedor(): HasOne
    {
        return $this->hasOne(Vendedor::class, 'id_usuario', 'id_usuario');
    }

    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class, 'id_usuario', 'id_usuario');
    }

    public function historialesEtapa(): HasMany
    {
        return $this->hasMany(HistorialEtapa::class, 'id_usuario', 'id_usuario');
    }

    public function historialesEstadoPedido(): HasMany
    {
        return $this->hasMany(HistorialEstadoPedido::class, 'id_usuario', 'id_usuario');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'id_usuario', 'id_usuario');
    }

    public function alertasResueltas(): HasMany
    {
        return $this->hasMany(Alerta::class, 'id_usuario_resuelve', 'id_usuario');
    }
}
