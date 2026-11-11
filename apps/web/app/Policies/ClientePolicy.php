<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Usuario;

/**
 * Qué puede hacer cada rol con un cliente del CRM (RN-10). La visibilidad la
 * resuelve antes `Cliente::visiblePara()` (404 si es ajeno); la policy decide
 * las acciones sobre un cliente ya visible.
 */
class ClientePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tieneRol(Rol::ADMINISTRADOR, Rol::VENDEDOR);
    }

    public function view(Usuario $usuario, Cliente $cliente): bool
    {
        return $this->esDeSuCartera($usuario, $cliente);
    }

    public function update(Usuario $usuario, Cliente $cliente): bool
    {
        return $this->esDeSuCartera($usuario, $cliente);
    }

    /** Solo el administrador reasigna carteras (RN-10). */
    public function reasignar(Usuario $usuario, Cliente $cliente): bool
    {
        return $usuario->esAdministrador();
    }

    private function esDeSuCartera(Usuario $usuario, Cliente $cliente): bool
    {
        return $usuario->esAdministrador()
            || ($usuario->tieneRol(Rol::VENDEDOR) && $cliente->id_vendedor === $usuario->vendedor?->id_vendedor);
    }
}
