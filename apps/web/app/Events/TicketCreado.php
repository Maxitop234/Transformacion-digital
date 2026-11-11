<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se emite cada vez que nace un ticket (T-018), después de confirmar la
 * transacción. T-021 le engancha la clasificación con IA en un listener
 * encolado: una falla de la IA nunca impide crear el ticket (API.md §3.2).
 *
 * Solo lleva el identificador: el listener relee el ticket y decide si
 * clasificarlo (los que ya nacen clasificados por regla se saltan).
 */
final class TicketCreado implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $idTicket) {}
}
