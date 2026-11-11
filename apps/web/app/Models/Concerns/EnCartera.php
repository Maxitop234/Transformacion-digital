<?php

namespace App\Models\Concerns;

use App\Domain\Clientes\AlcanceCartera;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scope reutilizable de RN-10 para los modelos que pertenecen a un cliente:
 *
 *     Cliente::visiblePara($usuario)->findOrFail($id);   // 404 si es ajeno
 *     Oportunidad::visiblePara($usuario)->get();
 *
 * El modelo puede redefinir `columnaCliente()` si la columna no se llama
 * `id_cliente`.
 */
trait EnCartera
{
    /**
     * @param  Builder<static>  $consulta
     * @return Builder<static>
     */
    public function scopeVisiblePara(Builder $consulta, Usuario $usuario): Builder
    {
        return AlcanceCartera::aplicar($consulta, $usuario, $this->columnaCliente());
    }

    protected function columnaCliente(): string
    {
        return 'id_cliente';
    }
}
