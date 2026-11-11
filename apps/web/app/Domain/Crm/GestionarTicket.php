<?php

namespace App\Domain\Crm;

use App\Models\Rol;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Models\Vendedor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Atención manual de un ticket en el CRM (T-018, RN-11): clasificar o
 * reclasificar, asignar, cambiar de estado y cerrar.
 *
 * - Clasificar a mano deja `clasificado_por_ia = false`.
 * - Un ticket se resuelve o cierra solo si ya tiene categoría.
 * - Cerrar registra `cerrado_en`; un ticket cerrado ya no cambia (409).
 * - Asignar: el administrador elige cualquier vendedor activo; el vendedor
 *   solo puede tomarlo para sí (el ticket ya es de su cartera, RN-10).
 */
final class GestionarTicket
{
    /**
     * @param  array{categoria?: string|null, prioridad?: string|null, estado?: string|null, id_vendedor?: int|null}  $datos
     */
    public function __invoke(Ticket $ticket, array $datos, Usuario $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $datos, $actor) {
            $ticket = Ticket::whereKey($ticket->id_ticket)->lockForUpdate()->firstOrFail();

            if ($ticket->estado === Ticket::CERRADO) {
                throw new HttpException(409, 'El ticket está cerrado y ya no se puede modificar.');
            }

            $categoria = $datos['categoria'] ?? null;
            $prioridad = $datos['prioridad'] ?? null;

            if (($categoria !== null && $categoria !== $ticket->categoria) || ($prioridad !== null && $prioridad !== $ticket->prioridad)) {
                $ticket->categoria = $categoria ?? $ticket->categoria;
                $ticket->prioridad = $prioridad ?? $ticket->prioridad;
                $ticket->clasificado_por_ia = false;
            }

            if (array_key_exists('id_vendedor', $datos) && $datos['id_vendedor'] !== null && $datos['id_vendedor'] !== $ticket->id_vendedor) {
                $ticket->id_vendedor = $this->vendedorAsignable((int) $datos['id_vendedor'], $actor);
            }

            $estado = $datos['estado'] ?? null;

            if ($estado !== null && $estado !== $ticket->estado) {
                if (in_array($estado, ['resuelto', Ticket::CERRADO], true) && $ticket->categoria === Ticket::SIN_CLASIFICAR) {
                    throw ValidationException::withMessages(['categoria' => 'Clasifica el ticket antes de resolverlo o cerrarlo.']);
                }

                $ticket->estado = $estado;
                $ticket->cerrado_en = $estado === Ticket::CERRADO ? now() : null;
            }

            $ticket->save();

            return $ticket;
        });
    }

    private function vendedorAsignable(int $idVendedor, Usuario $actor): int
    {
        if ($actor->tieneRol(Rol::VENDEDOR) && $idVendedor !== $actor->vendedor?->id_vendedor) {
            throw new AuthorizationException('Solo puedes tomar el ticket para ti; reasignarlo a otro vendedor lo hace el administrador.');
        }

        if (! Vendedor::whereKey($idVendedor)->where('activo', true)->exists()) {
            throw ValidationException::withMessages(['id_vendedor' => 'Elige un vendedor activo.']);
        }

        return $idVendedor;
    }
}
