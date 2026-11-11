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
use App\Models\Producto;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crea un pedido «Pendiente» con las mismas reglas por cualquier canal
 * (RN-08): desde el carrito del portal (`__invoke`, T-011) o como pedido
 * telefónico que el vendedor ingresa en el CRM (`telefonico`, T-014). Todo
 * en una transacción: si algo falla, no queda pedido, ni reserva, ni
 * carrito convertido.
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
     * Pedido del portal a partir del carrito activo del cliente.
     *
     * @throws ValidationException datos inválidos o bajo el mínimo (422)
     * @throws SinStock sin disponible para reservar (409)
     */
    public function __invoke(PerfilPrecio $perfil, Usuario $actor, DatosPedido $datos): Pedido
    {
        $this->validarEntrada($perfil, $datos);

        return DB::transaction(function () use ($perfil, $actor, $datos) {
            $carrito = Carrito::where('id_cliente', $perfil->cliente->id_cliente)->where('estado', 'activo')->lockForUpdate()->first();
            $cantidades = $carrito
                ? ItemCarrito::where('id_carrito', $carrito->id_carrito)->orderBy('id_producto')->pluck('cantidad', 'id_producto')->map(fn ($c) => (string) $c)->all()
                : [];

            if ($cantidades === []) {
                throw ValidationException::withMessages(['pedido' => 'Tu carrito está vacío.']);
            }

            $pedido = $this->registrar($perfil, $actor, $datos, $cantidades, 'portal', $carrito->id_carrito, null, 'Pedido creado en el portal');

            $carrito->estado = 'convertido';
            $carrito->save();

            return $pedido;
        });
    }

    /**
     * Pedido telefónico (RN-08): el vendedor compra a nombre del cliente con
     * los mismos precios y reglas. Queda con `canal = crm` y quién lo
     * registró (`id_vendedor_registra`; nulo si lo ingresa un administrador
     * sin ficha de vendedor, el historial guarda igual su usuario).
     *
     * @param  array<int, string>  $cantidades  id_producto => cantidad
     *
     * @throws ValidationException datos inválidos o bajo el mínimo (422)
     * @throws SinStock sin disponible para reservar (409)
     */
    public function telefonico(PerfilPrecio $perfil, Usuario $vendedor, DatosPedido $datos, array $cantidades): Pedido
    {
        $this->validarEntrada($perfil, $datos);

        $cantidades = array_filter($cantidades, fn ($cantidad) => Montos::centesimas((string) $cantidad) > 0);

        if ($cantidades === []) {
            throw ValidationException::withMessages(['pedido' => 'Agrega al menos un producto con cantidad.']);
        }

        ksort($cantidades);

        return DB::transaction(fn () => $this->registrar(
            $perfil,
            $vendedor,
            $datos,
            array_map('strval', $cantidades),
            'crm',
            null,
            $vendedor->vendedor?->id_vendedor,
            'Pedido telefónico ingresado en el CRM',
        ));
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

    private function validarEntrada(PerfilPrecio $perfil, DatosPedido $datos): void
    {
        if ($perfil->cliente === null) {
            abort(403);
        }

        if (! $perfil->puedeComprar) {
            throw ValidationException::withMessages(['pedido' => 'La cuenta del cliente aún no puede comprar.']);
        }

        if ($perfil->esB2B && trim((string) $datos->ordenCompra) === '') {
            throw ValidationException::withMessages(['orden_compra' => 'Indica el número de la orden de compra.']);
        }

        if (! $this->calendario->admite(CarbonImmutable::parse($datos->fechaEntrega))) {
            throw ValidationException::withMessages(['fecha_entrega_solicitada' => sprintf(
                'La entrega se puede pedir desde el %s, de lunes a sábado.',
                $this->calendario->primeraFecha()->format('d-m-Y'),
            )]);
        }
    }

    /**
     * Núcleo común a todos los canales. Se llama dentro de una transacción.
     *
     * @param  array<int, string>  $cantidades  id_producto => cantidad
     */
    private function registrar(
        PerfilPrecio $perfil,
        Usuario $actor,
        DatosPedido $datos,
        array $cantidades,
        string $canal,
        ?int $idCarrito,
        ?int $idVendedorRegistra,
        string $comentario,
    ): Pedido {
        $cliente = $perfil->cliente;

        $direccion = DireccionDespacho::where('id_direccion', $datos->idDireccion)
            ->where('id_cliente', $cliente->id_cliente)
            ->where('activa', true)
            ->first();

        if ($direccion === null) {
            throw ValidationException::withMessages(['id_direccion' => 'Elige uno de los puntos de entrega activos del cliente.']);
        }

        // RN-01: precio de la lista vigente al momento de comprar.
        $productos = Producto::whereIn('id_producto', array_keys($cantidades))->get()->keyBy('id_producto');
        $precios = $this->precios->precios(array_keys($cantidades), $perfil->lista);
        $lineas = [];

        foreach ($cantidades as $idProducto => $cantidad) {
            $producto = $productos->get($idProducto);
            $precio = $producto?->activo ? $precios->get($idProducto) : null;

            if ($precio === null) {
                throw ValidationException::withMessages(['pedido' => ($producto?->nombre ?? 'Un producto').' no está disponible en la lista del cliente.']);
            }

            $lineas[$idProducto] = [(string) $precio, $cantidad];
        }

        $totales = Montos::totales($lineas);
        $this->exigirMinimo($perfil, $totales);

        $pedido = Pedido::create([
            'id_direccion' => $direccion->id_direccion,
            'id_estado_pedido' => EstadoPedido::idDe(EstadoPedido::PENDIENTE),
            'id_carrito' => $idCarrito,
            'id_vendedor_registra' => $idVendedorRegistra,
            'canal' => $canal,
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

        $this->transiciones->registrarInicio($pedido, $actor->id_usuario, $comentario);

        if ($perfil->esB2B) {
            Pago::create([
                'id_pedido' => $pedido->id_pedido,
                'monto' => $totales['total'],
                'metodo' => 'credito_30d',
                'estado' => 'pendiente',
                'referencia_externa' => trim($datos->ordenCompra),
                'fecha_hora' => now(),
            ]);
        }

        return $pedido;
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
