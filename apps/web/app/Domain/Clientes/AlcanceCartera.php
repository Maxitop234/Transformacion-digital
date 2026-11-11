<?php

namespace App\Domain\Clientes;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

/**
 * RN-10 · Alcance de la cartera: qué clientes puede ver cada usuario.
 *
 * - Administrador y despachador: todos (el despachador necesita los puntos de
 *   entrega; su acceso a la gestión comercial lo cortan los gates).
 * - Vendedor: solo los clientes asignados a él (`cliente.id_vendedor`).
 * - Cliente del portal: solo él mismo.
 *
 * Se aplica siempre en la consulta del servidor (SECURITY.md §6), así un
 * identificador ajeno no se encuentra y responde 404.
 */
final class AlcanceCartera
{
    /**
     * Restringe una consulta cuya tabla tiene `$columnaCliente` (por defecto
     * `id_cliente`) a los clientes visibles para el usuario.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $consulta
     * @return Builder<TModel>
     */
    public static function aplicar(Builder $consulta, Usuario $usuario, string $columnaCliente = 'id_cliente'): Builder
    {
        $columna = $consulta->qualifyColumn($columnaCliente);

        return match (true) {
            $usuario->tieneRol(Rol::ADMINISTRADOR, Rol::DESPACHADOR) => $consulta,
            $usuario->tieneRol(Rol::VENDEDOR) => $consulta->whereIn($columna, self::clientesDelVendedor($usuario)),
            $usuario->tieneRol(...Rol::CLIENTES) => $consulta->where($columna, $usuario->cliente?->id_cliente),
            default => $consulta->whereRaw('false'),
        };
    }

    /**
     * Subconsulta con los `id_cliente` de la cartera del vendedor (vacía si
     * el usuario no tiene ficha de vendedor).
     *
     * @return Builder<Cliente>
     */
    private static function clientesDelVendedor(Usuario $usuario): Builder
    {
        return Cliente::query()
            ->select('id_cliente')
            ->where('id_vendedor', $usuario->vendedor?->id_vendedor);
    }
}
