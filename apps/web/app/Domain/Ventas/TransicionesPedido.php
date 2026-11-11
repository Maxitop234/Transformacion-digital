<?php

namespace App\Domain\Ventas;

use App\Domain\Catalogo\ReservasStock;
use App\Models\EstadoPedido as Estado;
use App\Models\HistorialEstadoPedido;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio único de transiciones de estado del pedido (BUSINESS_RULES.md §4,
 * RN-09). Ningún otro código cambia `pedido.id_estado_pedido`.
 *
 * Cada cambio: bloquea el pedido, valida que la transición exista (si no,
 * `TransicionInvalida` → 409), valida que el actor pueda hacerla (si no,
 * 403), actualiza el estado y escribe `historial_estado_pedido`.
 *
 * Efectos sobre el stock (DATA_MODEL.md §2, mismo criterio que los datos de
 * demo): al cancelar se libera la reserva; al entregar se libera y sale del
 * stock (`salida`); al rechazar la carga se libera y se da de baja (`merma`).
 *
 * Cancelar, rechazar y reprogramar una entrega fallida exigen comentario.
 * Los efectos sobre despachos y paradas los agrega T-017 alrededor de este
 * mismo servicio.
 */
final class TransicionesPedido
{
    /** Actor `null`: el sistema (trabajos programados, pasarela). */
    public const SISTEMA = 'sistema';

    /** Dueño del pedido: el cliente de su dirección de despacho. */
    public const CLIENTE = 'cliente';

    /**
     * origen => destino => quién puede (además del administrador, que puede todo).
     *
     * @var array<string, array<string, list<string>>>
     */
    public const TRANSICIONES = [
        Estado::PENDIENTE => [
            Estado::CONFIRMADO => [self::SISTEMA, Rol::VENDEDOR],
            Estado::CANCELADO => [self::SISTEMA, self::CLIENTE],
        ],
        Estado::CONFIRMADO => [
            Estado::EN_PREPARACION => [Rol::DESPACHADOR],
            Estado::CANCELADO => [self::CLIENTE, Rol::VENDEDOR],
        ],
        Estado::EN_PREPARACION => [
            Estado::EN_RUTA => [Rol::DESPACHADOR],
        ],
        Estado::EN_RUTA => [
            Estado::ENTREGADO => [Rol::DESPACHADOR],
            Estado::RECHAZADO => [Rol::DESPACHADOR],
            Estado::CONFIRMADO => [Rol::DESPACHADOR],
        ],
    ];

    /**
     * Pasos que exigen explicar el porqué (quién, cuándo y por qué).
     *
     * @var list<array{0: string, 1: string}>
     */
    public const CON_COMENTARIO = [
        [Estado::PENDIENTE, Estado::CANCELADO],
        [Estado::CONFIRMADO, Estado::CANCELADO],
        [Estado::EN_RUTA, Estado::RECHAZADO],
        [Estado::EN_RUTA, Estado::CONFIRMADO],
    ];

    public function __construct(private readonly ReservasStock $reservas) {}

    public static function exigeComentario(string $origen, string $destino): bool
    {
        return in_array([$origen, $destino], self::CON_COMENTARIO, true);
    }

    /**
     * Estados a los que el actor puede llevar el pedido desde su estado actual.
     *
     * @return list<string>
     */
    public function disponibles(Pedido $pedido, Usuario $actor): array
    {
        $origen = $pedido->estadoPedido()->value('nombre');

        return array_values(array_filter(
            array_keys(self::TRANSICIONES[$origen] ?? []),
            fn (string $destino) => $this->puede($actor, self::TRANSICIONES[$origen][$destino], $pedido),
        ));
    }

    /**
     * @param  Usuario|null  $actor  null = el sistema
     *
     * @throws TransicionInvalida
     * @throws AuthorizationException
     */
    public function cambiar(Pedido $pedido, string $destino, ?Usuario $actor, ?string $comentario = null): Pedido
    {
        return DB::transaction(function () use ($pedido, $destino, $actor, $comentario) {
            $pedido = Pedido::whereKey($pedido->id_pedido)->lockForUpdate()->firstOrFail();
            $origen = $pedido->estadoPedido()->value('nombre');

            $permitidos = self::TRANSICIONES[$origen][$destino] ?? null;

            if ($permitidos === null) {
                throw new TransicionInvalida($origen, $destino);
            }

            if (! $this->puede($actor, $permitidos, $pedido)) {
                throw new AuthorizationException('Tu rol no puede hacer este cambio de estado.');
            }

            if (self::exigeComentario($origen, $destino) && trim((string) $comentario) === '') {
                throw ValidationException::withMessages(['comentario' => "Indica el motivo para pasar de «{$origen}» a «{$destino}»."]);
            }

            $idUsuario = $actor?->id_usuario ?? self::idUsuarioSistema();

            $pedido->id_estado_pedido = Estado::idDe($destino);
            $pedido->save();

            HistorialEstadoPedido::create([
                'id_pedido' => $pedido->id_pedido,
                'id_estado_pedido' => $pedido->id_estado_pedido,
                'id_usuario' => $idUsuario,
                'fecha_hora' => now(),
                'comentario' => $comentario !== null && trim($comentario) !== '' ? trim($comentario) : null,
            ]);

            match ($destino) {
                Estado::CANCELADO => $this->reservas->liberar($pedido, $idUsuario),
                Estado::ENTREGADO => $this->reservas->despachar($pedido, $idUsuario, 'salida'),
                Estado::RECHAZADO => $this->reservas->despachar($pedido, $idUsuario, 'merma'),
                default => null,
            };

            return $pedido;
        });
    }

    /** Historial del estado inicial «Pendiente» (lo usa `CrearPedido`). */
    public function registrarInicio(Pedido $pedido, int $idUsuario, ?string $comentario = null): void
    {
        HistorialEstadoPedido::create([
            'id_pedido' => $pedido->id_pedido,
            'id_estado_pedido' => Estado::idDe(Estado::PENDIENTE),
            'id_usuario' => $idUsuario,
            'fecha_hora' => now(),
            'comentario' => $comentario,
        ]);
    }

    public static function idUsuarioSistema(): int
    {
        return Usuario::where('email', CatalogoSeeder::EMAIL_SISTEMA)->valueOrFail('id_usuario');
    }

    /** @param  list<string>  $permitidos */
    private function puede(?Usuario $actor, array $permitidos, Pedido $pedido): bool
    {
        if ($actor === null) {
            return in_array(self::SISTEMA, $permitidos, true);
        }

        if ($actor->esAdministrador()) {
            return true;
        }

        if ($actor->tieneRol(...Rol::CLIENTES)) {
            return in_array(self::CLIENTE, $permitidos, true)
                && $pedido->direccionDespacho()->value('id_cliente') === $actor->cliente?->id_cliente;
        }

        // RN-10: el vendedor solo actúa sobre pedidos de su cartera.
        if ($actor->tieneRol(Rol::VENDEDOR)) {
            return in_array(Rol::VENDEDOR, $permitidos, true)
                && Pedido::visiblePara($actor)->whereKey($pedido->id_pedido)->exists();
        }

        return in_array($actor->nombreRol(), $permitidos, true);
    }
}
