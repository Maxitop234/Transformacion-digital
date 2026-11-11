<?php

namespace App\Jobs;

use App\Domain\Ia\ClasificarTicket;
use App\Domain\Ia\IaNoDisponible;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Clasifica un ticket recién creado en el worker de colas (ADR-0010).
 * Se encola al recibir `TicketCreado` (ver `AppServiceProvider`).
 *
 * Degradación (API.md §3.1, §3.2): si la IA no está disponible se reintenta
 * hasta dos veces con espera; agotados los intentos, el ticket queda
 * `sin_clasificar` para un ejecutivo. El trabajo nunca lanza la excepción:
 * con la cola `sync` eso rompería la creación del ticket.
 */
final class ClasificarTicketConIa implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Segundos entre reintentos. */
    private const ESPERA = 10;

    public function __construct(public readonly int $idTicket) {}

    public function handle(ClasificarTicket $clasificar): void
    {
        try {
            $clasificar($this->idTicket);
        } catch (IaNoDisponible $e) {
            $intento = $this->job?->attempts() ?? 1;

            if ($this->job !== null && $this->job->getConnectionName() !== 'sync' && $intento < config('ia.intentos_clasificacion')) {
                $this->release(self::ESPERA * $intento);

                return;
            }

            Log::warning('Ticket sin clasificar por la IA; queda para clasificación manual.', [
                'id_ticket' => $this->idTicket,
                'motivo' => $e->getMessage(),
            ]);
        }
    }
}
