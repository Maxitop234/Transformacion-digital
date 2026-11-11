<?php

namespace App\Domain\Clientes;

use App\Models\Cliente;
use App\Models\ContactoCliente;
use Illuminate\Support\Facades\DB;

/**
 * Agrega un contacto al cliente. Si se marca como principal, el anterior
 * deja de serlo en la misma transacción (índice único parcial: a lo más un
 * principal por cliente).
 */
final class AgregarContacto
{
    /** @param  array{nombre: string, cargo?: string|null, email: string, telefono?: string|null, es_principal?: bool}  $datos */
    public function __invoke(Cliente $cliente, array $datos): ContactoCliente
    {
        return DB::transaction(function () use ($cliente, $datos) {
            $principal = (bool) ($datos['es_principal'] ?? false);

            if ($principal) {
                ContactoCliente::where('id_cliente', $cliente->id_cliente)->where('es_principal', true)->update(['es_principal' => false]);
            }

            return ContactoCliente::create([
                'id_cliente' => $cliente->id_cliente,
                'nombre' => trim($datos['nombre']),
                'cargo' => $datos['cargo'] ?? null,
                'email' => trim($datos['email']),
                'telefono' => $datos['telefono'] ?? null,
                'es_principal' => $principal,
            ]);
        });
    }
}
