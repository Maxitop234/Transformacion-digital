<?php

namespace App\Domain\Crm;

use App\Events\TicketCreado;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Único punto de creación de tickets (T-018, RN-11): desde el portal, desde
 * el rechazo de una carga (`RegistrarResultadoParada`) y, en T-021, desde el
 * asistente.
 *
 * - Nace `abierto`, con `clasificado_por_ia = false` y asignado al vendedor
 *   del cliente (puede estar vacío si el cliente no tiene cartera).
 * - Sin categoría explícita nace `sin_clasificar` y prioridad `media`, a la
 *   espera de la IA o de un ejecutivo. Una regla del sistema puede fijarla
 *   (la carga rechazada nace `reclamo`/`alta`, API.md §1.4).
 * - El pedido, si viene, debe ser del mismo cliente: quien llama ya lo
 *   resolvió con `visiblePara()` (un pedido ajeno responde 404 antes de aquí).
 * - Emite `TicketCreado` al confirmar la transacción.
 */
final class CrearTicket
{
    public function __invoke(
        Cliente $cliente,
        string $asunto,
        string $descripcion,
        ?Pedido $pedido = null,
        ?string $categoria = null,
        ?string $prioridad = null,
    ): Ticket {
        if ($pedido !== null && $pedido->direccionDespacho()->value('id_cliente') !== $cliente->id_cliente) {
            throw new InvalidArgumentException('El pedido no pertenece al cliente del ticket.');
        }

        return DB::transaction(function () use ($cliente, $asunto, $descripcion, $pedido, $categoria, $prioridad) {
            $ticket = Ticket::create([
                'id_cliente' => $cliente->id_cliente,
                'id_pedido' => $pedido?->id_pedido,
                'id_vendedor' => $cliente->id_vendedor,
                'asunto' => trim($asunto),
                'descripcion' => trim($descripcion),
                'categoria' => $categoria ?? Ticket::SIN_CLASIFICAR,
                'prioridad' => $prioridad ?? 'media',
                'estado' => 'abierto',
                'clasificado_por_ia' => false,
                'creado_en' => now(),
            ]);

            TicketCreado::dispatch($ticket->id_ticket);

            return $ticket;
        });
    }
}
