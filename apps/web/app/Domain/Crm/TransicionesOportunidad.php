<?php

namespace App\Domain\Crm;

use App\Domain\Ventas\TransicionesPedido;
use App\Models\EstadoPedido;
use App\Models\EtapaPipeline;
use App\Models\HistorialEtapa;
use App\Models\Interaccion;
use App\Models\Oportunidad;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Servicio único de cambios de etapa de la oportunidad (BUSINESS_RULES.md §5,
 * RN-09). Ningún otro código cambia `oportunidad.id_etapa`.
 *
 * Cada cambio bloquea la oportunidad, rechaza las cerradas (409), valida que
 * la transición exista y que se cumpla su condición (422 con el motivo),
 * actualiza la etapa y escribe `historial_etapa`.
 *
 * - Prospecto nuevo → Contactado: hay al menos una interacción.
 * - Contactado → Calificado: tipo de cliente y monto estimado definidos.
 * - Calificado → Propuesta: cliente validado (activo; B2B con `fecha_validacion`) y con lista de precios.
 * - Propuesta → Negociación: el cliente respondió (interacción posterior a la entrada en «Propuesta»).
 * - Propuesta o Negociación → Ganado: hay un pedido vinculado (`pedido.id_oportunidad`).
 * - Cualquier etapa abierta → Perdido: motivo obligatorio.
 * - Ganado o Perdido → otra: no permitido. La única excepción es deshacer un
 *   vínculo automático del sistema (`deshacerVinculo`).
 *
 * Una interacción cuenta para la oportunidad si está ligada a ella o al mismo
 * cliente sin oportunidad (mismo criterio que el panel, T-013).
 */
final class TransicionesOportunidad
{
    public const PROSPECTO = 'Prospecto nuevo';

    public const CONTACTADO = 'Contactado';

    public const CALIFICADO = 'Calificado';

    public const PROPUESTA = 'Propuesta';

    public const NEGOCIACION = 'Negociación';

    public const GANADO = 'Ganado';

    public const PERDIDO = 'Perdido';

    /** @var array<string, list<string>> origen => destinos posibles */
    public const TRANSICIONES = [
        self::PROSPECTO => [self::CONTACTADO, self::PERDIDO],
        self::CONTACTADO => [self::CALIFICADO, self::PERDIDO],
        self::CALIFICADO => [self::PROPUESTA, self::PERDIDO],
        self::PROPUESTA => [self::NEGOCIACION, self::GANADO, self::PERDIDO],
        self::NEGOCIACION => [self::GANADO, self::PERDIDO],
    ];

    /**
     * Destinos desde la etapa actual con el motivo por el que aún no se puede
     * (null si se puede). Para mostrar en la vista.
     *
     * @return array<string, string|null>
     */
    public function disponibles(Oportunidad $oportunidad): array
    {
        $origen = $oportunidad->etapaPipeline()->value('nombre');
        $destinos = [];

        foreach (self::TRANSICIONES[$origen] ?? [] as $destino) {
            $destinos[$destino] = $this->incumplimiento($oportunidad, $origen, $destino, ['motivo' => 'x', 'id_pedido' => null]);
        }

        return $destinos;
    }

    /**
     * @param  array{motivo?: string|null, comentario?: string|null, id_pedido?: int|null}  $datos
     * @param  Usuario|null  $actor  null = el sistema
     */
    public function cambiar(Oportunidad $oportunidad, string $destino, ?Usuario $actor, array $datos = []): Oportunidad
    {
        return DB::transaction(function () use ($oportunidad, $destino, $actor, $datos) {
            $oportunidad = Oportunidad::whereKey($oportunidad->id_oportunidad)->lockForUpdate()->firstOrFail();
            $origen = $oportunidad->etapaPipeline()->value('nombre');

            $this->autorizar($oportunidad, $actor);

            if (! isset(self::TRANSICIONES[$origen])) {
                throw new HttpException(409, "La oportunidad está cerrada («{$origen}») y no se reabre: crea una nueva.");
            }

            if (! in_array($destino, self::TRANSICIONES[$origen], true)) {
                throw new HttpException(409, "Una oportunidad en «{$origen}» no puede pasar a «{$destino}».");
            }

            // Ganar eligiendo un pedido: se vincula primero y luego se valida la condición.
            if ($destino === self::GANADO && ($datos['id_pedido'] ?? null) !== null) {
                $this->vincularPedido($oportunidad, (int) $datos['id_pedido']);
            }

            if ($motivo = $this->incumplimiento($oportunidad, $origen, $destino, $datos)) {
                throw ValidationException::withMessages([$destino === self::PERDIDO ? 'motivo' : 'etapa' => $motivo]);
            }

            $etapa = EtapaPipeline::where('nombre', $destino)->firstOrFail();
            $oportunidad->id_etapa = $etapa->id_etapa;

            if ($etapa->es_cierre) {
                $oportunidad->fecha_cierre = now();
            }

            if ($destino === self::PERDIDO) {
                $oportunidad->motivo_perdida = trim((string) $datos['motivo']);
            }

            $oportunidad->save();

            $this->historial($oportunidad, $etapa, $actor, $destino === self::PERDIDO
                ? 'Perdida: '.trim((string) $datos['motivo'])
                : (filled($datos['comentario'] ?? null) ? trim($datos['comentario']) : null));

            return $oportunidad;
        });
    }

    /**
     * Gana la oportunidad con un pedido (BUSINESS_RULES.md §6). Lo usa
     * `GanarOportunidadConPedido` con el actor sistema.
     */
    public function ganarConPedido(Oportunidad $oportunidad, Pedido $pedido, ?Usuario $actor): Oportunidad
    {
        return $this->cambiar($oportunidad, self::GANADO, $actor, [
            'id_pedido' => $pedido->id_pedido,
            'comentario' => "Ganada con el pedido N.º {$pedido->id_pedido}",
        ]);
    }

    /**
     * Deshace un vínculo automático: la oportunidad la ganó el sistema con un
     * pedido y el vendedor no está de acuerdo. Desvincula los pedidos, vuelve
     * a la etapa anterior y deja constancia en el historial.
     */
    public function deshacerVinculo(Oportunidad $oportunidad, Usuario $actor, string $motivo): Oportunidad
    {
        return DB::transaction(function () use ($oportunidad, $actor, $motivo) {
            $oportunidad = Oportunidad::whereKey($oportunidad->id_oportunidad)->lockForUpdate()->firstOrFail();
            $this->autorizar($oportunidad, $actor);

            $historial = HistorialEtapa::where('id_oportunidad', $oportunidad->id_oportunidad)
                ->orderByDesc('fecha_hora')->orderByDesc('id_historial')->limit(2)->get();
            $ultimo = $historial->first();

            if ($oportunidad->etapaPipeline()->value('nombre') !== self::GANADO
                || $ultimo === null || $ultimo->id_usuario !== TransicionesPedido::idUsuarioSistema()) {
                throw new HttpException(409, 'Solo se puede deshacer una oportunidad ganada automáticamente por un pedido.');
            }

            if (trim($motivo) === '') {
                throw ValidationException::withMessages(['motivo' => 'Indica por qué el pedido no corresponde a esta oportunidad.']);
            }

            $anterior = $historial->get(1)?->id_etapa ?? EtapaPipeline::where('nombre', self::NEGOCIACION)->value('id_etapa');
            $pedidos = Pedido::where('id_oportunidad', $oportunidad->id_oportunidad)->pluck('id_pedido');
            Pedido::where('id_oportunidad', $oportunidad->id_oportunidad)->update(['id_oportunidad' => null]);

            $oportunidad->update(['id_etapa' => $anterior, 'fecha_cierre' => null]);
            $etapa = EtapaPipeline::findOrFail($anterior);

            $this->historial($oportunidad, $etapa, $actor, sprintf(
                'Vínculo deshecho con el pedido N.º %s: %s',
                $pedidos->implode(', '),
                trim($motivo),
            ));

            return $oportunidad;
        });
    }

    /** Puede deshacerse: está en «Ganado» y el último cambio lo hizo el sistema. */
    public function puedeDeshacer(Oportunidad $oportunidad): bool
    {
        if ($oportunidad->etapaPipeline()->value('nombre') !== self::GANADO) {
            return false;
        }

        return HistorialEtapa::where('id_oportunidad', $oportunidad->id_oportunidad)
            ->orderByDesc('fecha_hora')->orderByDesc('id_historial')
            ->value('id_usuario') === TransicionesPedido::idUsuarioSistema();
    }

    /** Pedidos del cliente que se pueden vincular para ganar a mano: sin oportunidad y no cancelados ni rechazados. */
    public static function pedidosVinculables(Oportunidad $oportunidad): Builder
    {
        return Pedido::query()
            ->whereNull('id_oportunidad')
            ->whereHas('direccionDespacho', fn (Builder $q) => $q->where('id_cliente', $oportunidad->id_cliente))
            ->whereHas('estadoPedido', fn (Builder $q) => $q->whereNotIn('nombre', [EstadoPedido::CANCELADO, EstadoPedido::RECHAZADO]));
    }

    /**
     * Condición de la transición; null si se cumple.
     *
     * @param  array{motivo?: string|null, id_pedido?: int|null}  $datos
     */
    private function incumplimiento(Oportunidad $oportunidad, string $origen, string $destino, array $datos): ?string
    {
        $cliente = $oportunidad->cliente()->with(['tipoCliente', 'listaPrecio'])->first();

        return match ($destino) {
            self::CONTACTADO => $this->interacciones($oportunidad)->exists()
                ? null : 'Registra al menos una interacción con el cliente antes de marcarla como contactada.',
            self::CALIFICADO => match (true) {
                $cliente?->tipoCliente === null => 'El cliente no tiene tipo definido.',
                $oportunidad->monto_estimado === null || (float) $oportunidad->monto_estimado <= 0 => 'Define el monto estimado antes de calificarla.',
                default => null,
            },
            self::PROPUESTA => match (true) {
                $cliente?->estado_cliente !== 'activo' => 'El cliente aún no está validado: valida la empresa antes de enviar la propuesta.',
                $cliente->tipoCliente->codigo === 'B2B' && $cliente->fecha_validacion === null => 'La empresa aún no está validada.',
                $cliente->listaPrecio === null => 'El cliente no tiene lista de precios asignada.',
                default => null,
            },
            self::NEGOCIACION => $this->interacciones($oportunidad)->where('fecha_hora', '>=', $this->entradaA($oportunidad, self::PROPUESTA))->exists()
                ? null : 'Registra la respuesta del cliente a la propuesta (una interacción) antes de pasar a negociación.',
            self::GANADO => Pedido::where('id_oportunidad', $oportunidad->id_oportunidad)->exists()
                ? null : 'Para ganarla debe haber un pedido vinculado: elige un pedido del cliente.',
            self::PERDIDO => trim((string) ($datos['motivo'] ?? '')) === ''
                ? 'Indica el motivo de la pérdida.' : null,
            default => "Transición no contemplada: {$origen} → {$destino}.",
        };
    }

    private function vincularPedido(Oportunidad $oportunidad, int $idPedido): void
    {
        $pedido = self::pedidosVinculables($oportunidad)->whereKey($idPedido)->lockForUpdate()->first();

        if ($pedido === null) {
            throw ValidationException::withMessages(['id_pedido' => 'El pedido no es del cliente, ya tiene oportunidad o está cancelado.']);
        }

        $pedido->update(['id_oportunidad' => $oportunidad->id_oportunidad]);
    }

    /** @return Builder<Interaccion> */
    private function interacciones(Oportunidad $oportunidad): Builder
    {
        return Interaccion::where(fn (Builder $q) => $q
            ->where('id_oportunidad', $oportunidad->id_oportunidad)
            ->orWhere(fn (Builder $q) => $q->where('id_cliente', $oportunidad->id_cliente)->whereNull('id_oportunidad')));
    }

    /** Cuándo entró por última vez a la etapa (o la creación, si no hay historial). */
    private function entradaA(Oportunidad $oportunidad, string $etapa): Carbon
    {
        $fecha = HistorialEtapa::where('id_oportunidad', $oportunidad->id_oportunidad)
            ->where('id_etapa', EtapaPipeline::where('nombre', $etapa)->value('id_etapa'))
            ->max('fecha_hora');

        return $fecha ? Carbon::parse($fecha) : $oportunidad->fecha_creacion;
    }

    /** RN-10: el vendedor actúa solo sobre su cartera; el despachador nunca. */
    private function autorizar(Oportunidad $oportunidad, ?Usuario $actor): void
    {
        if ($actor === null || $actor->esAdministrador()) {
            return;
        }

        if (! $actor->tieneRol(Rol::VENDEDOR) || ! Oportunidad::visiblePara($actor)->whereKey($oportunidad->id_oportunidad)->exists()) {
            throw new AuthorizationException('No puedes modificar esta oportunidad.');
        }
    }

    private function historial(Oportunidad $oportunidad, EtapaPipeline $etapa, ?Usuario $actor, ?string $comentario): void
    {
        HistorialEtapa::create([
            'id_oportunidad' => $oportunidad->id_oportunidad,
            'id_etapa' => $etapa->id_etapa,
            'id_usuario' => $actor?->id_usuario ?? TransicionesPedido::idUsuarioSistema(),
            'fecha_hora' => now(),
            'comentario' => $comentario !== null ? mb_substr($comentario, 0, 300) : null,
        ]);
    }
}
