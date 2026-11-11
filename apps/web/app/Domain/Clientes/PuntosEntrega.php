<?php

namespace App\Domain\Clientes;

use App\Models\Cliente;
use App\Models\DireccionDespacho;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Puntos de entrega del cliente en el portal (T-012, `/mis-direcciones`,
 * RN-06). Solo B2B tiene varios (BUSINESS_RULES.md §2); B2C mantiene su
 * única dirección. Los pedidos guardan `id_direccion`, así que un punto no se
 * borra: se desactiva y deja de ofrecerse en el checkout. Siempre queda al
 * menos uno activo para poder comprar.
 */
final class PuntosEntrega
{
    /** @param  array{alias: string, calle: string, id_comuna: int, referencia?: string|null, ventana_inicio?: string|null, ventana_fin?: string|null}  $datos */
    public function agregar(Cliente $cliente, array $datos): DireccionDespacho
    {
        return DireccionDespacho::create([
            'id_cliente' => $cliente->id_cliente,
            'id_comuna' => $datos['id_comuna'],
            'alias' => trim($datos['alias']),
            'calle' => trim($datos['calle']),
            'referencia' => isset($datos['referencia']) && trim($datos['referencia']) !== '' ? trim($datos['referencia']) : null,
            'ventana_inicio' => $datos['ventana_inicio'] ?? null,
            'ventana_fin' => $datos['ventana_fin'] ?? null,
            'activa' => true,
        ]);
    }

    public function cambiarActiva(DireccionDespacho $direccion, bool $activa): DireccionDespacho
    {
        return DB::transaction(function () use ($direccion, $activa) {
            // Bloquea los puntos del cliente para que dos bajas simultáneas no lo dejen sin ninguno.
            $activas = DireccionDespacho::where('id_cliente', $direccion->id_cliente)
                ->where('activa', true)->lockForUpdate()->pluck('id_direccion');

            if (! $activa && $activas->count() === 1 && $activas->first() === $direccion->id_direccion) {
                throw ValidationException::withMessages(['direccion' => 'Debes mantener al menos un punto de entrega activo.']);
            }

            $direccion->activa = $activa;
            $direccion->save();

            return $direccion;
        });
    }
}
