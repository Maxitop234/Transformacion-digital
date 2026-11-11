<?php

namespace App\Domain\Crm;

use App\Models\Cliente;
use App\Models\Interaccion;
use App\Models\Oportunidad;
use App\Models\Usuario;
use Illuminate\Validation\ValidationException;

/**
 * Registra una llamada, correo, reunión, visita o mensaje con el cliente
 * (T-014). Toda interacción queda con fecha, tipo y vendedor: el que la
 * registra o, si la registra un administrador, el vendedor del cliente.
 * Puede ligarse a una oportunidad del mismo cliente.
 */
final class RegistrarInteraccion
{
    public const TIPOS = ['llamada', 'correo', 'reunion', 'visita', 'mensaje'];

    public function __invoke(Cliente $cliente, Usuario $actor, string $tipo, string $resumen, ?int $idOportunidad): Interaccion
    {
        $idVendedor = $actor->vendedor?->id_vendedor ?? $cliente->id_vendedor;

        if ($idVendedor === null) {
            throw ValidationException::withMessages(['tipo' => 'Asigna un vendedor al cliente antes de registrar interacciones.']);
        }

        if ($idOportunidad !== null && ! Oportunidad::whereKey($idOportunidad)->where('id_cliente', $cliente->id_cliente)->exists()) {
            throw ValidationException::withMessages(['id_oportunidad' => 'La oportunidad no es de este cliente.']);
        }

        return Interaccion::create([
            'id_cliente' => $cliente->id_cliente,
            'id_vendedor' => $idVendedor,
            'id_oportunidad' => $idOportunidad,
            'tipo' => $tipo,
            'resumen' => trim($resumen),
            'fecha_hora' => now(),
        ]);
    }
}
