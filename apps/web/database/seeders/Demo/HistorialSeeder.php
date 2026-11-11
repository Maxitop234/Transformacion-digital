<?php

namespace Database\Seeders\Demo;

use App\Models\Camion;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Despacho;
use App\Models\DespachoPedido;
use App\Models\DetalleListaPrecio;
use App\Models\DetallePedido;
use App\Models\EstadoPedido;
use App\Models\EtapaPipeline;
use App\Models\HistorialEstadoPedido;
use App\Models\HistorialEtapa;
use App\Models\Interaccion;
use App\Models\Inventario;
use App\Models\ItemCarrito;
use App\Models\MovimientoInventario;
use App\Models\Oportunidad;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Models\Vendedor;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Historial sintético de las últimas 8 semanas para que el CRM y el BI tengan
 * datos: pedidos con su ciclo de vida completo (BUSINESS_RULES.md §4), pagos,
 * movimientos de stock, carritos, despachos, oportunidades con su historial de
 * etapas, interacciones y tickets.
 *
 * Convención de `movimiento_inventario.cantidad`: `ingreso` suma y `salida` y
 * `merma` restan al stock físico (`stock_disponible`); `reserva` suma y
 * `liberacion` resta a `stock_reservado`.
 */
class HistorialSeeder extends Seeder
{
    /** Pedidos por día hábil (sábado la mitad, domingo ninguno). */
    private const PEDIDOS_POR_DIA = [2, 6];

    /** Máximo de pedidos por despacho (paradas de una ruta). */
    private const PARADAS_POR_DESPACHO = 6;

    private const TEXTOS_INTERACCION = [
        'llamada' => 'Llamada de seguimiento: revisamos el último pedido y la frecuencia de compra.',
        'correo' => 'Se envió por correo la lista de precios vigente y la ficha de productos congelados.',
        'reunion' => 'Reunión con el encargado de compras para revisar consumos del mes.',
        'visita' => 'Visita a bodega del cliente: se revisó la recepción en andén y la cadena de frío.',
        'mensaje' => 'Mensaje para confirmar la ventana de recepción de la próxima entrega.',
    ];

    /** [asunto, descripción, categoría, prioridad] */
    private const TICKETS = [
        ['Consulta por despacho', 'Quisiera saber en qué horario llega mi pedido de esta semana.', 'consulta', 'baja'],
        ['Producto con fecha corta', 'Recibimos yogures con vencimiento muy próximo, solicitamos revisión.', 'reclamo', 'media'],
        ['Cambiar cantidad del pedido', 'Necesitamos aumentar la cantidad de pechuga de pollo del pedido en curso.', 'cambio_pedido', 'media'],
        ['Cotización para evento', 'Solicitamos cotización de helados y postres para un evento de fin de mes.', 'cotizacion', 'baja'],
        ['Caja dañada', 'Una caja de empanadas llegó con el embalaje roto.', 'reclamo', 'alta'],
        ['Disponibilidad de salmón', 'Consulto si habrá stock de filete de salmón la próxima semana.', 'consulta', 'baja'],
        ['Cambio de dirección de entrega', 'Este pedido debe llegar a la sucursal y no a la bodega central.', 'cambio_pedido', 'alta'],
        ['Factura del mes', 'No encuentro la factura del último pedido en el portal.', 'otro', 'media'],
    ];

    private Azar $azar;

    private CarbonImmutable $ahora;

    /** @var array<string, int> */
    private array $estados;

    /** @var array<string, int> */
    private array $etapas;

    private int $sistema;

    private int $despachador;

    /** @var array<int, int> id_vendedor => id_usuario */
    private array $usuarioVendedor;

    /** @var array<int, float> */
    private array $stockDisponible = [];

    /** @var array<int, float> */
    private array $stockReservado = [];

    /** @var array<int, int> id_cliente => id_oportunidad ganada (para el primer pedido) */
    private array $oportunidadGanada = [];

    private int $numeroOc = 4500;

    public function run(): void
    {
        $this->azar = new Azar(2026);
        $this->ahora = CarbonImmutable::now();
        $this->estados = EstadoPedido::pluck('id_estado_pedido', 'nombre')->all();
        $this->etapas = EtapaPipeline::orderBy('orden')->pluck('id_etapa', 'nombre')->all();
        $this->sistema = DemoSeeder::idUsuarioSistema();
        $this->despachador = Usuario::where('email', 'despacho@cofrial.example')->value('id_usuario');
        $this->usuarioVendedor = Vendedor::pluck('id_usuario', 'id_vendedor')->all();

        foreach (Inventario::all() as $fila) {
            $this->stockDisponible[$fila->id_producto] = (float) $fila->stock_disponible;
            $this->stockReservado[$fila->id_producto] = 0.0;
        }

        $clientes = Cliente::with(['direccionesDespacho', 'tipoCliente'])->get();

        $this->oportunidades($clientes);
        $planes = $this->planificarPedidos($clientes->where('estado_cliente', 'activo'));
        $this->asignarDespachos($planes);
        $pedidos = $this->escribirPedidos($planes);
        $this->carritosAbiertos($clientes);
        $this->actualizarInventario();
        $this->interacciones($clientes);
        $this->tickets($pedidos, $clientes);
    }

    // ------------------------------------------------------------------ CRM

    /**
     * @param  Collection<int, Cliente>  $clientes
     */
    private function oportunidades(Collection $clientes): void
    {
        foreach ($clientes->where('tipoCliente.codigo', 'B2B') as $cliente) {
            $vendedor = $cliente->id_vendedor;

            if ($cliente->estado_cliente === 'activo') {
                // La venta que convirtió al cliente: llegó hasta Ganado al validarlo.
                $cierre = CarbonImmutable::parse($cliente->fecha_validacion);
                $op = $this->crearOportunidad($cliente, $vendedor, 'Ganado', $cierre->subDays(18), $cierre, 'Contrato de abastecimiento '.$cierre->year);
                $this->oportunidadGanada[$cliente->id_cliente] = $op->id_oportunidad;

                if ($this->azar->probabilidad(0.7)) {
                    $etapa = $this->azar->uno(['Contactado', 'Calificado', 'Propuesta', 'Negociación']);
                    $this->crearOportunidad($cliente, $vendedor, $etapa, DemoSeeder::inicio()->addDays($this->azar->entero(5, 40)), null, 'Ampliación a línea de congelados');
                }

                if ($this->azar->probabilidad(0.3)) {
                    $this->crearOportunidad($cliente, $vendedor, 'Perdido', DemoSeeder::inicio()->addDays($this->azar->entero(0, 20)), null, 'Abastecimiento de productos del mar');
                }
            } elseif ($cliente->estado_cliente === 'pendiente_validacion') {
                // RN-02: el registro web crea la oportunidad «Prospecto nuevo».
                $this->crearOportunidad($cliente, $vendedor, 'Prospecto nuevo', CarbonImmutable::parse($cliente->fecha_registro), null, 'Registro web de empresa', 'portal');
            } else {
                $this->crearOportunidad($cliente, $vendedor, $this->azar->uno(['Contactado', 'Calificado']), CarbonImmutable::parse($cliente->fecha_registro), null, 'Primer abastecimiento', $this->azar->uno(['telefono', 'referido', 'vendedor']));
            }
        }
    }

    private function crearOportunidad(Cliente $cliente, int $vendedor, string $etapaFinal, CarbonImmutable $creada, ?CarbonImmutable $cierre, string $titulo, ?string $origen = null): Oportunidad
    {
        $recorrido = match ($etapaFinal) {
            'Perdido' => ['Prospecto nuevo', 'Contactado', 'Calificado', 'Perdido'],
            default => array_slice(array_keys($this->etapas), 0, array_search($etapaFinal, array_keys($this->etapas), true) + 1),
        };

        $paso = $cierre ? max(1, intdiv((int) $creada->diffInDays($cierre), max(1, count($recorrido) - 1))) : $this->azar->entero(2, 5);
        $fechas = [];
        $fecha = $creada;

        foreach ($recorrido as $etapa) {
            $fechas[$etapa] = min($fecha, $this->ahora->subHour());
            $fecha = $fecha->addDays($paso)->addHours($this->azar->entero(1, 6));
        }

        $calificada = in_array('Calificado', $recorrido, true);
        $ultima = end($fechas);

        $op = Oportunidad::create([
            'id_cliente' => $cliente->id_cliente,
            'id_vendedor' => $vendedor,
            'id_etapa' => $this->etapas[$etapaFinal],
            'titulo' => $titulo,
            'origen' => $origen ?? $this->azar->uno(['vendedor', 'telefono', 'referido']),
            // RN: el monto estimado se exige para pasar a «Calificado».
            'monto_estimado' => $calificada ? $this->azar->entero(15, 120) * 100_000 : null,
            'fecha_creacion' => $creada,
            'fecha_cierre_estimada' => $creada->addDays(45)->toDateString(),
            'fecha_cierre' => in_array($etapaFinal, ['Ganado', 'Perdido'], true) ? $ultima : null,
            'motivo_perdida' => $etapaFinal === 'Perdido' ? 'El cliente eligió otro proveedor por precio' : null,
        ]);

        foreach ($fechas as $etapa => $momento) {
            HistorialEtapa::create([
                'id_oportunidad' => $op->id_oportunidad,
                'id_etapa' => $this->etapas[$etapa],
                'id_usuario' => $etapa === 'Prospecto nuevo' && $op->origen === 'portal' ? $this->sistema : $this->usuarioVendedor[$vendedor],
                'fecha_hora' => $momento,
                'comentario' => $etapa === 'Prospecto nuevo' ? 'Oportunidad creada' : null,
            ]);
        }

        return $op;
    }

    // -------------------------------------------------------------- Pedidos

    /**
     * Arma en memoria cada pedido con su línea de tiempo prevista. Lo que cae
     * después de «ahora» se recorta al escribir, así los pedidos recientes
     * quedan en estados intermedios.
     *
     * @param  Collection<int, Cliente>  $clientes
     * @return list<array<string, mixed>>
     */
    private function planificarPedidos(Collection $clientes): array
    {
        $b2c = $clientes->where('tipoCliente.codigo', 'B2C')->values()->all();
        $b2b = $clientes->where('tipoCliente.codigo', 'B2B')->values()->all();
        $productos = Producto::all()->groupBy('id_rango');
        $precios = [];

        foreach (DetalleListaPrecio::all() as $p) {
            $precios[$p->id_lista_precio][$p->id_producto] = (float) $p->precio_unitario;
        }

        $planes = [];

        for ($dia = DemoSeeder::inicio(); $dia->lessThan($this->ahora); $dia = $dia->addDay()) {
            [$min, $max] = self::PEDIDOS_POR_DIA;
            $cantidad = match (true) {
                $dia->isSunday() => 0,
                $dia->isSaturday() => intdiv($this->azar->entero($min, $max), 2),
                default => $this->azar->entero($min, $max),
            };

            for ($i = 0; $i < $cantidad; $i++) {
                $esB2b = $this->azar->probabilidad(0.6);
                /** @var Cliente $cliente */
                $cliente = $this->azar->uno($esB2b ? $b2b : $b2c);

                // B2B solo compra después de su validación (RN-02).
                $inicio = $dia->setTime($this->azar->entero(8, 19), $this->azar->entero(0, 59));
                if ($esB2b && $inicio->lessThan($cliente->fecha_validacion)) {
                    continue;
                }
                if ($inicio->greaterThan($this->ahora)) {
                    continue;
                }

                $rango = $this->azar->uno($productos->keys()->all());
                $lineas = [];

                foreach ($this->azar->varios($productos[$rango]->all(), $this->azar->entero(1, 4)) as $producto) {
                    $lineas[$producto->id_producto] = [
                        'cantidad' => $this->cantidad($producto->unidad_medida, $esB2b),
                        'precio' => $precios[$cliente->id_lista_precio][$producto->id_producto],
                        'paso' => $esB2b ? 5 : 1,
                    ];
                }

                $this->cumplirPedidoMinimo($lineas, $esB2b);

                $canal = $esB2b && $this->azar->probabilidad(0.4) ? 'crm' : 'portal';
                $confirmado = $esB2b
                    ? $inicio->addHours($this->azar->entero(2, 20))
                    : $inicio->addMinutes($this->azar->entero(3, 25));

                $planes[] = [
                    'cliente' => $cliente,
                    'b2b' => $esB2b,
                    'direccion' => $this->azar->uno($cliente->direccionesDespacho->all())->id_direccion,
                    'rango' => $rango,
                    'lineas' => $lineas,
                    'canal' => $canal,
                    'inicio' => $inicio,
                    'confirmado' => $confirmado,
                    // Entrega desde el siguiente día hábil (RN-06).
                    'entrega' => $this->siguienteDiaHabil($confirmado),
                    'desenlace' => $this->azar->ponderado(['entregado' => 86, 'cancelado' => 9, 'rechazado' => 5]),
                ];
            }
        }

        return $planes;
    }

    private function cantidad(string $unidad, bool $esB2b): int
    {
        return match ($unidad) {
            'kg' => $esB2b ? $this->azar->entero(10, 40) : $this->azar->entero(1, 3),
            'caja' => $esB2b ? $this->azar->entero(5, 15) : $this->azar->entero(1, 2),
            default => $esB2b ? $this->azar->entero(10, 30) : $this->azar->entero(1, 4),
        };
    }

    /**
     * RN-03: sube la cantidad de la primera línea hasta llegar al mínimo.
     *
     * @param  array<int, array{cantidad: int, precio: float, paso: int}>  $lineas
     */
    private function cumplirPedidoMinimo(array &$lineas, bool $esB2b): void
    {
        $minimo = config('negocio.pedido_minimo.'.($esB2b ? 'b2b' : 'b2c'));
        $primera = array_key_first($lineas);

        while ($this->neto($lineas) < $minimo) {
            $lineas[$primera]['cantidad'] += $lineas[$primera]['paso'];
        }
    }

    /**
     * @param  array<int, array{cantidad: int|float, precio: float}>  $lineas
     */
    private function neto(array $lineas): float
    {
        return array_sum(array_map(fn ($l) => $l['cantidad'] * $l['precio'], $lineas));
    }

    private function siguienteDiaHabil(CarbonImmutable $desde): CarbonImmutable
    {
        $dia = $desde->addDay()->startOfDay();

        return $dia->isSunday() ? $dia->addDay() : $dia;
    }

    /**
     * RN-07: un despacho agrupa pedidos del mismo rango de conservación. Se
     * agrupan por día de entrega y rango, hasta PARADAS_POR_DESPACHO paradas.
     *
     * @param  list<array<string, mixed>>  $planes
     */
    private function asignarDespachos(array &$planes): void
    {
        $camiones = Camion::where('activo', true)->orderBy('camion_id')->pluck('camion_id')->all();
        $conductores = Conductor::orderBy('id_conductor')->pluck('id_conductor')->all();
        $grupos = [];
        $despachos = [];

        foreach ($planes as $i => $plan) {
            if ($plan['desenlace'] !== 'cancelado') {
                $grupos[$plan['entrega']->toDateString().'|'.$plan['rango']][] = $i;
            }
        }

        $n = 0;
        ksort($grupos);

        foreach ($grupos as $indices) {
            foreach (array_chunk($indices, self::PARADAS_POR_DESPACHO) as $tanda) {
                $dia = $planes[$tanda[0]]['entrega'];
                $preparacion = $dia->setTime(7, 0)->addMinutes(20 * ($n % 6));
                $salida = $preparacion->addMinutes($this->azar->entero(60, 120));
                $despacho = [
                    'camion' => $camiones[$n % count($camiones)],
                    'conductor' => $conductores[$n % count($conductores)],
                    'dia' => $dia,
                    'salida' => $salida,
                ];

                foreach ($tanda as $parada => $i) {
                    $planes[$i]['despacho'] = $n;
                    $planes[$i]['parada'] = $parada + 1;
                    $planes[$i]['preparacion'] = $preparacion;
                    $planes[$i]['en_ruta'] = $salida;
                    $planes[$i]['llegada'] = $salida->addMinutes(45 * ($parada + 1) + $this->azar->entero(0, 20));
                }

                $despachos[$n++] = $despacho;
            }
        }

        foreach ($planes as &$plan) {
            if (isset($plan['despacho'])) {
                $plan['despacho_datos'] = $despachos[$plan['despacho']];
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $planes
     * @return list<Pedido>
     */
    private function escribirPedidos(array $planes): array
    {
        $pedidos = [];
        $despachos = [];
        $iva = config('negocio.iva_pct') / 100;

        foreach ($planes as $plan) {
            /** @var Cliente $cliente */
            $cliente = $plan['cliente'];
            $inicio = $plan['inicio'];
            $creador = $plan['canal'] === 'crm' ? $this->usuarioVendedor[$cliente->id_vendedor] : $cliente->id_usuario;
            $carrito = $plan['canal'] === 'portal' ? $this->carritoConvertido($cliente, $plan['lineas'], $inicio) : null;

            $pedido = Pedido::create([
                'id_direccion' => $plan['direccion'],
                'id_estado_pedido' => $this->estados['Pendiente'],
                'id_carrito' => $carrito?->id_carrito,
                'id_oportunidad' => $plan['b2b'] ? $this->primeraCompra($cliente) : null,
                'id_vendedor_registra' => $plan['canal'] === 'crm' ? $cliente->id_vendedor : null,
                'canal' => $plan['canal'],
                'fecha_pedido' => $inicio,
                'fecha_entrega_solicitada' => $plan['entrega']->toDateString(),
            ]);

            foreach ($plan['lineas'] as $idProducto => $linea) {
                DetallePedido::create([
                    'id_pedido' => $pedido->id_pedido,
                    'id_producto' => $idProducto,
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario_pactado' => $linea['precio'],
                ]);
                $this->moverStock($idProducto, 'reserva', $linea['cantidad'], $inicio, $creador, $pedido->id_pedido);
            }

            $this->historial($pedido, 'Pendiente', $inicio, $creador, null);
            $total = round($this->neto($plan['lineas']) * (1 + $iva));
            $estado = 'Pendiente';

            // Pendiente → Confirmado (pago B2C aprobado u OC B2B validada) o Cancelado.
            $cancelaAntes = $plan['desenlace'] === 'cancelado' && ! $plan['b2b'];
            if ($cancelaAntes) {
                $momento = $inicio->addMinutes(config('negocio.reserva_stock_minutos_b2c'));
                if ($momento->lessThanOrEqualTo($this->ahora)) {
                    $this->pago($pedido, $this->azar->uno(['tarjeta', 'transferencia']), $total, 'rechazado', $inicio->addMinutes(5));
                    $estado = $this->cancelar($pedido, $plan, $momento, $this->sistema, 'Sin pago dentro del plazo de reserva');
                }
            } elseif ($plan['confirmado']->lessThanOrEqualTo($this->ahora)) {
                if ($plan['b2b']) {
                    $this->pago($pedido, 'credito_30d', $total, 'aprobado', $plan['confirmado'], 'OC-'.$this->numeroOc++);
                    $this->historial($pedido, 'Confirmado', $plan['confirmado'], $this->usuarioVendedor[$cliente->id_vendedor], 'Orden de compra validada');
                } else {
                    $this->pago($pedido, $this->azar->uno(['tarjeta', 'tarjeta', 'transferencia']), $total, 'aprobado', $plan['confirmado'], 'TRX-'.$pedido->id_pedido);
                    $this->historial($pedido, 'Confirmado', $plan['confirmado'], $this->sistema, 'Pago en línea aprobado');
                }
                $estado = 'Confirmado';

                if ($plan['desenlace'] === 'cancelado') {
                    $momento = $plan['confirmado']->addHours(2);
                    if ($momento->lessThanOrEqualTo($this->ahora)) {
                        $estado = $this->cancelar($pedido, $plan, $momento, $creador, 'Cancelado por el cliente antes de la preparación');
                    }
                }
            } elseif ($plan['b2b']) {
                // OC recibida, a la espera de que el vendedor la valide.
                $this->pago($pedido, 'credito_30d', $total, 'pendiente', $inicio, 'OC-'.$this->numeroOc++);
            }

            if ($estado === 'Confirmado' && isset($plan['despacho'])) {
                $estado = $this->avanzarLogistica($pedido, $plan, $despachos);
            }

            $pedido->update(['id_estado_pedido' => $this->estados[$estado]]);
            $pedidos[] = $pedido;
        }

        return $pedidos;
    }

    /**
     * Confirmado → En preparación → En ruta → Entregado / Rechazado.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<int, Despacho>  $despachos
     */
    private function avanzarLogistica(Pedido $pedido, array $plan, array &$despachos): string
    {
        if ($plan['preparacion']->greaterThan($this->ahora)) {
            return 'Confirmado';
        }

        $despacho = $despachos[$plan['despacho']] ??= $this->crearDespacho($plan['despacho_datos']);
        $parada = DespachoPedido::create([
            'id_despacho' => $despacho->id_despacho,
            'id_pedido' => $pedido->id_pedido,
            'orden_parada' => $plan['parada'],
        ]);
        $this->historial($pedido, 'En preparación', $plan['preparacion'], $this->despachador, "Asignado al despacho {$despacho->id_despacho}");

        if ($plan['en_ruta']->greaterThan($this->ahora)) {
            return 'En preparación';
        }

        $this->historial($pedido, 'En ruta', $plan['en_ruta'], $this->despachador, "Salida del camión {$despacho->camion_id}");

        if ($plan['llegada']->greaterThan($this->ahora)) {
            return 'En ruta';
        }

        $rechazado = $plan['desenlace'] === 'rechazado';

        foreach ($plan['lineas'] as $idProducto => $linea) {
            $this->moverStock($idProducto, 'liberacion', -$linea['cantidad'], $plan['llegada'], $this->despachador, $pedido->id_pedido);
            $this->moverStock($idProducto, $rechazado ? 'merma' : 'salida', -$linea['cantidad'], $plan['llegada'], $this->despachador, $pedido->id_pedido);
        }

        $parada->update([
            'estado_entrega' => $rechazado ? 'rechazado_temperatura' : 'entregado',
            'fecha_entrega' => $plan['llegada'],
            'motivo_falla' => $rechazado ? 'Temperatura de la carga fuera de rango al recibir' : null,
        ]);

        if ($rechazado) {
            $this->historial($pedido, 'Rechazado', $plan['llegada'], $this->despachador, 'Rechazo por temperatura fuera de rango');
            $this->ticketPorRechazo($pedido, $plan);
            $this->cerrarDespachoSiTermino($despacho, $plan['llegada']);

            return 'Rechazado';
        }

        $this->historial($pedido, 'Entregado', $plan['llegada'], $this->despachador, 'Recepción conforme');
        $this->cerrarDespachoSiTermino($despacho, $plan['llegada']);

        return 'Entregado';
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearDespacho(array $datos): Despacho
    {
        return Despacho::create([
            'camion_id' => $datos['camion'],
            'id_conductor' => $datos['conductor'],
            'estado' => $datos['salida']->lessThanOrEqualTo($this->ahora) ? 'en_ruta' : 'programado',
            'fecha_programada' => $datos['dia']->toDateString(),
            'salida_real' => $datos['salida']->lessThanOrEqualTo($this->ahora) ? $datos['salida'] : null,
        ]);
    }

    private function cerrarDespachoSiTermino(Despacho $despacho, CarbonImmutable $llegada): void
    {
        $pendientes = DespachoPedido::where('id_despacho', $despacho->id_despacho)->where('estado_entrega', 'pendiente')->exists();

        if (! $pendientes) {
            $despacho->update(['estado' => 'finalizado', 'llegada_real' => $llegada->addMinutes(40)]);
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function cancelar(Pedido $pedido, array $plan, CarbonImmutable $momento, int $usuario, string $motivo): string
    {
        foreach ($plan['lineas'] as $idProducto => $linea) {
            $this->moverStock($idProducto, 'liberacion', -$linea['cantidad'], $momento, $usuario, $pedido->id_pedido);
        }

        $this->historial($pedido, 'Cancelado', $momento, $usuario, $motivo);

        return 'Cancelado';
    }

    private function historial(Pedido $pedido, string $estado, CarbonImmutable $momento, ?int $usuario, ?string $comentario): void
    {
        HistorialEstadoPedido::create([
            'id_pedido' => $pedido->id_pedido,
            'id_estado_pedido' => $this->estados[$estado],
            'id_usuario' => $usuario,
            'fecha_hora' => $momento,
            'comentario' => $comentario,
        ]);
    }

    private function pago(Pedido $pedido, string $metodo, float $monto, string $estado, CarbonImmutable $momento, ?string $referencia = null): void
    {
        Pago::create([
            'id_pedido' => $pedido->id_pedido,
            'monto' => $monto,
            'metodo' => $metodo,
            'estado' => $estado,
            'referencia_externa' => $referencia,
            'fecha_hora' => $momento,
        ]);
    }

    /** La primera compra de una empresa cierra la oportunidad ganada; las siguientes son recompras. */
    private function primeraCompra(Cliente $cliente): ?int
    {
        $id = $this->oportunidadGanada[$cliente->id_cliente] ?? null;
        unset($this->oportunidadGanada[$cliente->id_cliente]);

        return $id;
    }

    /**
     * @param  array<int, array{cantidad: int}>  $lineas
     */
    private function carritoConvertido(Cliente $cliente, array $lineas, CarbonImmutable $pedido): Carrito
    {
        $carrito = Carrito::create([
            'id_cliente' => $cliente->id_cliente,
            'estado' => 'convertido',
            'creado_en' => $pedido->subMinutes($this->azar->entero(5, 40)),
            'actualizado_en' => $pedido,
        ]);

        foreach ($lineas as $idProducto => $linea) {
            ItemCarrito::create(['id_carrito' => $carrito->id_carrito, 'id_producto' => $idProducto, 'cantidad' => $linea['cantidad']]);
        }

        return $carrito;
    }

    /**
     * Carritos que no terminaron en pedido: algunos abandonados (RN-04) y
     * uno activo por cliente para mostrar en el portal.
     *
     * @param  Collection<int, Cliente>  $clientes
     */
    private function carritosAbiertos(Collection $clientes): void
    {
        $productos = Producto::pluck('id_producto')->all();
        $abandono = config('negocio.carrito_abandono_horas');

        foreach ($clientes->where('tipoCliente.codigo', 'B2C')->values() as $i => $cliente) {
            $activo = $i % 2 === 0;
            $momento = $activo ? $this->ahora->subMinutes(30 * ($i + 1)) : $this->ahora->subHours($abandono + 24 * $i);
            $carrito = Carrito::create([
                'id_cliente' => $cliente->id_cliente,
                'estado' => $activo ? 'activo' : 'abandonado',
                'creado_en' => $momento->subMinutes(15),
                'actualizado_en' => $momento,
            ]);

            foreach ($this->azar->varios($productos, $this->azar->entero(1, 3)) as $idProducto) {
                ItemCarrito::create(['id_carrito' => $carrito->id_carrito, 'id_producto' => $idProducto, 'cantidad' => $this->azar->entero(1, 3)]);
            }
        }
    }

    // ---------------------------------------------------------- Inventario

    private function moverStock(int $idProducto, string $tipo, float $cantidad, CarbonImmutable $momento, int $usuario, ?int $idPedido): void
    {
        if ($tipo === 'reserva' && $cantidad > $this->stockDisponible[$idProducto] - $this->stockReservado[$idProducto]) {
            // Reposición: llega mercadería antes de que el stock no alcance.
            $reposicion = max(200.0, $cantidad * 4);
            $this->stockDisponible[$idProducto] += $reposicion;
            MovimientoInventario::create(['id_producto' => $idProducto, 'id_usuario' => $this->sistema, 'tipo' => 'ingreso', 'cantidad' => $reposicion, 'fecha_hora' => $momento->subHours(2)]);
        }

        match ($tipo) {
            'reserva', 'liberacion' => $this->stockReservado[$idProducto] += $cantidad,
            default => $this->stockDisponible[$idProducto] += $cantidad,
        };

        MovimientoInventario::create([
            'id_producto' => $idProducto,
            'id_pedido' => $idPedido,
            'id_usuario' => $usuario,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'fecha_hora' => $momento,
        ]);
    }

    private function actualizarInventario(): void
    {
        foreach ($this->stockDisponible as $idProducto => $disponible) {
            Inventario::where('id_producto', $idProducto)->update([
                'stock_disponible' => $disponible,
                'stock_reservado' => $this->stockReservado[$idProducto],
                'actualizado_en' => $this->ahora,
            ]);
        }
    }

    // ---------------------------------------------- Interacciones y tickets

    /**
     * @param  Collection<int, Cliente>  $clientes
     */
    private function interacciones(Collection $clientes): void
    {
        foreach ($clientes->where('tipoCliente.codigo', 'B2B') as $cliente) {
            $oportunidad = Oportunidad::where('id_cliente', $cliente->id_cliente)->orderByDesc('fecha_creacion')->value('id_oportunidad');
            $desde = max(DemoSeeder::inicio(), CarbonImmutable::parse($cliente->fecha_registro));
            $dias = max(1, (int) $desde->diffInDays($this->ahora));
            $cantidad = $cliente->estado_cliente === 'activo' ? $this->azar->entero(3, 8) : $this->azar->entero(1, 3);

            for ($i = 0; $i < $cantidad; $i++) {
                $tipo = $this->azar->uno(array_keys(self::TEXTOS_INTERACCION));
                $momento = $desde->addDays($this->azar->entero(0, $dias))->setTime($this->azar->entero(9, 18), $this->azar->entero(0, 59));

                Interaccion::create([
                    'id_cliente' => $cliente->id_cliente,
                    'id_vendedor' => $cliente->id_vendedor,
                    'id_oportunidad' => $this->azar->probabilidad(0.5) ? $oportunidad : null,
                    'tipo' => $tipo,
                    'resumen' => self::TEXTOS_INTERACCION[$tipo],
                    'fecha_hora' => min($momento, $this->ahora->subMinutes(10)),
                ]);
            }
        }
    }

    /**
     * @param  list<Pedido>  $pedidos
     * @param  Collection<int, Cliente>  $clientes
     */
    private function tickets(array $pedidos, Collection $clientes): void
    {
        $vendedores = array_keys($this->usuarioVendedor);
        $activos = $clientes->where('estado_cliente', 'activo')->keyBy('id_cliente');
        $direcciones = [];

        foreach ($activos as $c) {
            foreach ($c->direccionesDespacho as $d) {
                $direcciones[$d->id_direccion] = $c->id_cliente;
            }
        }

        for ($i = 0; $i < 24; $i++) {
            $pedido = $this->azar->uno($pedidos);
            $cliente = $activos[$direcciones[$pedido->id_direccion]];
            [$asunto, $descripcion, $categoria, $prioridad] = $this->azar->uno(self::TICKETS);
            $creado = CarbonImmutable::parse($pedido->fecha_pedido)->addHours($this->azar->entero(2, 72));

            if ($creado->greaterThan($this->ahora)) {
                $creado = $this->ahora->subHours($this->azar->entero(1, 12));
            }

            $antiguedad = $creado->diffInDays($this->ahora);
            // Los recientes aún esperan clasificación (T-018, T-021).
            $sinClasificar = $antiguedad < 2 && $this->azar->probabilidad(0.6);
            $estado = match (true) {
                $sinClasificar => 'abierto',
                $antiguedad > 7 => $this->azar->uno(['resuelto', 'cerrado', 'cerrado']),
                default => $this->azar->uno(['abierto', 'en_proceso']),
            };

            Ticket::create([
                'id_cliente' => $cliente->id_cliente,
                'id_pedido' => $this->azar->probabilidad(0.7) ? $pedido->id_pedido : null,
                'id_vendedor' => $cliente->id_vendedor ?? ($estado === 'abierto' ? null : $this->azar->uno($vendedores)),
                'asunto' => $asunto,
                'descripcion' => $descripcion,
                'categoria' => $sinClasificar ? 'sin_clasificar' : $categoria,
                'prioridad' => $sinClasificar ? 'media' : $prioridad,
                'estado' => $estado,
                'clasificado_por_ia' => ! $sinClasificar && $this->azar->probabilidad(0.6),
                'creado_en' => $creado,
                'cerrado_en' => in_array($estado, ['resuelto', 'cerrado'], true) ? $creado->addDays($this->azar->entero(1, 4)) : null,
            ]);
        }
    }

    /**
     * Un rechazo por temperatura abre un reclamo (BUSINESS_RULES.md §4).
     *
     * @param  array<string, mixed>  $plan
     */
    private function ticketPorRechazo(Pedido $pedido, array $plan): void
    {
        /** @var Cliente $cliente */
        $cliente = $plan['cliente'];
        $creado = $plan['llegada']->addMinutes(30);

        Ticket::create([
            'id_cliente' => $cliente->id_cliente,
            'id_pedido' => $pedido->id_pedido,
            'id_vendedor' => $cliente->id_vendedor,
            'asunto' => 'Rechazo de carga por temperatura',
            'descripcion' => 'La carga llegó fuera del rango de temperatura y se rechazó en la recepción.',
            'categoria' => 'reclamo',
            'prioridad' => 'alta',
            'estado' => $creado->diffInDays($this->ahora) > 5 ? 'cerrado' : 'en_proceso',
            'creado_en' => min($creado, $this->ahora),
            'cerrado_en' => $creado->diffInDays($this->ahora) > 5 ? $creado->addDays(2) : null,
        ]);
    }
}
