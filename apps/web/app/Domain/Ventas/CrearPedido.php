<?php

namespace App\Domain\Ventas;

use App\Domain\Catalogo\PerfilPrecio;
use App\Domain\Catalogo\Precios;
use App\Domain\Catalogo\ReservasStock;
use App\Models\Carrito;
use App\Models\DetallePedido;
use App\Models\DireccionDespacho;
use App\Models\EstadoPedido;
use App\Models\ItemCarrito;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Convierte el carrito activo en un pedido «Pendiente» (T-011), todo en una
 * transacción: si algo falla, no queda pedido, ni reserva, ni carrito
 * convertido.
 *
 * - RN-01: copia a `detalle_pedido.precio_unitario_pactado` el precio de la
 *   lista vigente del cliente en este momento.
 * - RN-03: exige el pedido mínimo (B2C sobre el total con IVA; B2B sobre el
 *   neto).
 * - RN-04: reserva el stock (`ReservasStock`) y deja el movimiento `reserva`.
 * - RN-05: B2B registra la orden de compra como pago `credito_30d`
 *   pendiente, a la espera del vendedor (T-016); B2C paga después en la
 *   pasarela simulada (`PagarPedido`).
 * - RN-06: fecha de entrega desde el siguiente día hábil; la ventana es la
 *   del punto de entrega elegido.
 * - RN-09: escribe la fila «Pendiente» de `historial_estado_pedido`.
 */
final class CrearPedido
{
    public function __construct(
        private readonly Precios $precios,
        private readonly ReservasStock $reservas,
        private readonly TransicionesPedido $transiciones,
        private readonly CalendarioEntregas $calendario,
    ) {}

    /**
     * @throws ValidationException datos inválidos o bajo el mínimo (422)
     * @throws SinStock sin disponible para reservar (409)
     */
    public function __invoke(PerfilPrecio $perfil, Usuario $actor, DatosPedido $datos): Pedido
    {
        $cliente = $perfil->cliente ?? abort(403);

        if (! $perfil->puedeComprar) {
            throw ValidationException::withMessages(['pedido' => 'Tu cuenta aún no puede comprar.']);
        }

        if ($perfil->esB2B && trim((string) $datos->ordenCompra) === '') {
            throw ValidationException::withMessages(['orden_compra' => 'Indica el número de tu orden de compra.']);
        }

        if (! $this->calendario->admite(CarbonImmutable::parse($datos->fechaEntrega))) {
            throw ValidationException::withMessages(['fecha_entrega_solicitada' => sprintf(
                'La entrega se puede pedir desde el %s, de lunes a sábado.',
                $this->calendario->primeraFecha()->format('d-m-Y'),
            )]);
        }

        return DB::transaction(function () use ($perfil, $cliente, $actor, $datos) {
            $carrito = Carrito::where('id_cliente', $cliente->id_cliente)->where('estado', 'activo')->lockForUpdate()->first();
            $items = $carrito
                ? ItemCarrito::where('id_carrito', $carrito->id_carrito)->with('producto')->orderBy('id_producto')->get()
                : collect();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['pedido' => 'Tu carrito está vacío.']);
            }

            $direccion = DireccionDespacho::where('id_direccion', $datos->idDireccion)
                ->where('id_cliente', $cliente->id_cliente)
                ->where('activa', true)
                ->first();

            if ($direccion === null) {
                throw ValidationException::withMessages(['id_direccion' => 'Elige uno de tus puntos de entrega activos.']);
            }

            // RN-01: precio de la lista vigente al momento de comprar.
            $precios = $this->precios->precios($items->pluck('id_producto'), $perfil->lista);
            $lineas = [];

            foreach ($items as $item) {
                $precio = $item->producto->activo ? $precios->get($item->id_producto) : null;

                if ($precio === null) {
                    throw ValidationException::withMessages(['pedido' => "{$item->producto->nombre} ya no está disponible en tu lista: quítalo del carrito."]);
                }

                $lineas[$item->id_producto] = [(string) $precio, (string) $item->cantidad];
            }

            $this->exigirMinimo($perfil, Montos::totales($lineas));

            $pedido = Pedido::create([
                'id_direccion' => $direccion->id_direccion,
                'id_estado_pedido' => EstadoPedido::idDe(EstadoPedido::PENDIENTE),
                'id_carrito' => $carrito->id_carrito,
                'canal' => 'portal',
                'fecha_pedido' => now(),
                'fecha_entrega_solicitada' => CarbonImmutable::parse($datos->fechaEntrega)->toDateString(),
                'observaciones' => $datos->observaciones !== null && trim($datos->observaciones) !== '' ? trim($datos->observaciones) : null,
            ]);

            foreach ($lineas as $idProducto => [$precio, $cantidad]) {
                DetallePedido::create([
                    'id_pedido' => $pedido->id_pedido,
                    'id_producto' => $idProducto,
                    'cantidad' => $cantidad,
                    'precio_unitario_pactado' => $precio,
                ]);
            }

            $this->reservas->reservar(
                $pedido,
                array_map(fn (array $linea) => Montos::centesimas($linea[1]), $lineas),
                $actor->id_usuario,
            );

            $this->transiciones->registrarInicio($pedido, $actor->id_usuario, 'Pedido creado en el portal');

            $carrito->estado = 'convertido';
            $carrito->save();

            if ($perfil->esB2B) {
                Pago::create([
                    'id_pedido' => $pedido->id_pedido,
                    'monto' => Montos::totales($lineas)['total'],
                    'metodo' => 'credito_30d',
                    'estado' => 'pendiente',
                    'referencia_externa' => trim($datos->ordenCompra),
                    'fecha_hora' => now(),
                ]);
            }

            return $pedido;
        });
    }

    /** Monto que se compara con el pedido mínimo según el perfil (RN-03). */
    public static function montoParaMinimo(PerfilPrecio $perfil, int $neto, int $total): int
    {
        return $perfil->esB2B ? $neto : $total;
    }

    public static function minimo(PerfilPrecio $perfil): int
    {
        return config($perfil->esB2B ? 'negocio.pedido_minimo.b2b' : 'negocio.pedido_minimo.b2c');
    }

    /** @param  array{neto: int, iva: int, total: int}  $totales */
    private function exigirMinimo(PerfilPrecio $perfil, array $totales): void
    {
        $minimo = self::minimo($perfil);

        if (self::montoParaMinimo($perfil, $totales['neto'], $totales['total']) < $minimo) {
            throw ValidationException::withMessages(['pedido' => sprintf(
                'El pedido mínimo es %s%s.',
                Precios::clp($minimo),
                $perfil->esB2B ? ' neto' : ' con IVA',
            )]);
        }
    }
}
