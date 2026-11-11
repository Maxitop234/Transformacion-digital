<?php

namespace App\Domain\Clientes;

use App\Models\Cliente;
use App\Models\ListaPrecio;
use App\Models\Usuario;
use App\Models\Vendedor;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RN-02 · Validación comercial de una empresa: asigna su lista
 * institucional y su vendedor, registra `fecha_validacion` y la deja
 * `activa`. Desde ese momento el portal le muestra su lista y le permite
 * comprar (`Precios::perfil()`).
 *
 * El vendedor solo valida empresas de su cartera y queda él como vendedor;
 * el administrador puede asignar a cualquier vendedor activo.
 */
final class ValidarEmpresa
{
    public function __invoke(Cliente $cliente, Usuario $actor, int $idLista, ?int $idVendedor): Cliente
    {
        return DB::transaction(function () use ($cliente, $actor, $idLista, $idVendedor) {
            $cliente = Cliente::whereKey($cliente->id_cliente)->with('tipoCliente')->lockForUpdate()->firstOrFail();

            if ($cliente->tipoCliente->codigo !== 'B2B' || $cliente->estado_cliente !== 'pendiente_validacion') {
                throw new EstadoClienteInvalido('Solo se valida una empresa pendiente de validación.');
            }

            if (blank($cliente->rut)) {
                throw ValidationException::withMessages(['rut' => 'La empresa necesita un RUT válido antes de validarla.']);
            }

            $lista = ListaPrecio::whereKey($idLista)->where('activa', true)->first();

            if ($lista === null || $lista->nombre === CatalogoSeeder::LISTA_PUBLICA || ! self::vigente($lista)) {
                throw ValidationException::withMessages(['id_lista_precio' => 'Elige una lista institucional activa y vigente.']);
            }

            $vendedor = $actor->esAdministrador()
                ? Vendedor::whereKey($idVendedor ?? $cliente->id_vendedor)->where('activo', true)->first()
                : $actor->vendedor;

            if ($vendedor === null) {
                throw ValidationException::withMessages(['id_vendedor' => 'Elige un vendedor activo.']);
            }

            $cliente->update([
                'id_lista_precio' => $lista->id_lista_precio,
                'id_vendedor' => $vendedor->id_vendedor,
                'estado_cliente' => 'activo',
                'fecha_validacion' => now(),
            ]);

            return $cliente;
        });
    }

    public static function vigente(ListaPrecio $lista): bool
    {
        $hoy = now(config('app.timezone'))->startOfDay();

        return $lista->vigente_desde->lte($hoy) && ($lista->vigente_hasta === null || $lista->vigente_hasta->gte($hoy));
    }
}
