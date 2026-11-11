<?php

namespace App\Domain\Crm;

use App\Models\Alerta;
use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\Oportunidad;
use App\Models\Pedido;
use App\Models\Ticket;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos del panel de inicio del CRM (T-013, API.md §1.4 `GET /crm`).
 *
 * Todo se filtra en el servidor con el alcance de cartera (RN-10,
 * `AlcanceCartera` vía `visiblePara()`): el vendedor ve solo sus clientes;
 * el administrador y el despachador, todos. Qué bloques ve cada rol lo
 * deciden los gates en la vista (el despachador no ve los comerciales).
 *
 * Umbrales en `config/negocio.php`: `oportunidad_sin_actividad_dias` (15) y
 * `conversion_ventana_dias` (90).
 */
final class PanelCrm
{
    /** Etiquetas de `pedido.canal` (RN-08). */
    public const CANALES = ['portal' => 'Portal', 'crm' => 'CRM (teléfono)'];

    /**
     * Indicadores comerciales.
     *
     * - Oportunidades abiertas: etapa que no es de cierre.
     * - Conversión: de las oportunidades creadas en la ventana, cuántas están
     *   en una etapa ganada (BUSINESS_RULES.md §7: ganadas ÷ creadas).
     * - Pedidos por canal: pedidos de la ventana, sin los cancelados, con su
     *   monto neto (Σ cantidad × precio pactado).
     * - Tickets abiertos: `abierto` o `en_proceso`.
     *
     * @return array{oportunidades_abiertas: int, conversion: array{creadas: int, ganadas: int, tasa: float|null}, pedidos_por_canal: array<string, array{pedidos: int, monto_neto: int}>, tickets_abiertos: int, ventana_dias: int}
     */
    public function indicadores(Usuario $usuario): array
    {
        $ventana = config('negocio.conversion_ventana_dias');
        $desde = now()->subDays($ventana);

        $creadas = Oportunidad::visiblePara($usuario)->where('fecha_creacion', '>=', $desde);
        $ganadas = (clone $creadas)->whereHas('etapaPipeline', fn (Builder $q) => $q->where('es_ganada', true))->count();
        $totalCreadas = $creadas->count();

        $porCanal = Pedido::visiblePara($usuario)
            ->where('fecha_pedido', '>=', $desde)
            ->whereHas('estadoPedido', fn (Builder $q) => $q->where('nombre', '<>', EstadoPedido::CANCELADO))
            ->leftJoinSub(
                DB::table('detalle_pedido')->selectRaw('id_pedido, sum(round(cantidad * precio_unitario_pactado)) AS neto')->groupBy('id_pedido'),
                'montos',
                'montos.id_pedido',
                '=',
                'pedido.id_pedido',
            )
            ->groupBy('pedido.canal')
            ->selectRaw('pedido.canal, count(*) AS pedidos, coalesce(sum(montos.neto), 0) AS monto_neto')
            ->get()
            ->keyBy('canal');

        return [
            'oportunidades_abiertas' => Oportunidad::visiblePara($usuario)
                ->whereHas('etapaPipeline', fn (Builder $q) => $q->where('es_cierre', false))
                ->count(),
            'conversion' => [
                'creadas' => $totalCreadas,
                'ganadas' => $ganadas,
                'tasa' => $totalCreadas > 0 ? round($ganadas / $totalCreadas * 100, 1) : null,
            ],
            'pedidos_por_canal' => collect(self::CANALES)->map(fn ($etiqueta, $canal) => [
                'pedidos' => (int) ($porCanal[$canal]->pedidos ?? 0),
                'monto_neto' => (int) ($porCanal[$canal]->monto_neto ?? 0),
            ])->all(),
            'tickets_abiertos' => Ticket::visiblePara($usuario)->whereIn('estado', ['abierto', 'en_proceso'])->count(),
            'ventana_dias' => $ventana,
        ];
    }

    /**
     * Empresas que esperan validación comercial (RN-02), las más antiguas primero.
     *
     * @return Collection<int, Cliente>
     */
    public function empresasPendientes(Usuario $usuario): Collection
    {
        return Cliente::visiblePara($usuario)
            ->where('estado_cliente', 'pendiente_validacion')
            ->whereHas('tipoCliente', fn (Builder $q) => $q->where('codigo', 'B2B'))
            ->with('vendedor')
            ->orderBy('fecha_registro')
            ->get();
    }

    /**
     * Oportunidades abiertas sin interacciones en `oportunidad_sin_actividad_dias`
     * o más. La última actividad es la interacción más reciente ligada a la
     * oportunidad o, si no se ligó a ninguna, al mismo cliente; sin
     * interacciones, la fecha de creación.
     *
     * @return Collection<int, Oportunidad> con `ultima_actividad` y `dias_sin_actividad`
     */
    public function oportunidadesSinActividad(Usuario $usuario): Collection
    {
        $dias = config('negocio.oportunidad_sin_actividad_dias');

        $ultimaInteraccion = DB::table('interaccion')
            ->selectRaw('max(interaccion.fecha_hora)')
            ->where(fn ($q) => $q
                ->whereColumn('interaccion.id_oportunidad', 'oportunidad.id_oportunidad')
                ->orWhere(fn ($q) => $q
                    ->whereNull('interaccion.id_oportunidad')
                    ->whereColumn('interaccion.id_cliente', 'oportunidad.id_cliente')));

        $abiertas = Oportunidad::visiblePara($usuario)
            ->whereHas('etapaPipeline', fn (Builder $q) => $q->where('es_cierre', false))
            ->select('oportunidad.*')
            ->selectSub($ultimaInteraccion, 'ultima_interaccion');

        // greatest() ignora los nulos: sin interacciones, cuenta la creación.
        return Oportunidad::query()
            ->fromSub($abiertas, 'oportunidad')
            ->select('oportunidad.*')
            ->selectRaw('greatest(fecha_creacion, ultima_interaccion) AS ultima_actividad')
            ->whereRaw('greatest(fecha_creacion, ultima_interaccion) <= ?', [now()->subDays($dias)])
            ->with(['cliente', 'etapaPipeline', 'vendedor'])
            ->orderBy('ultima_actividad')
            ->get()
            ->each(function (Oportunidad $op) {
                $op->ultima_actividad = CarbonImmutable::parse($op->ultima_actividad);
                $op->dias_sin_actividad = (int) $op->ultima_actividad->diffInDays(now());
            });
    }

    /** Alertas de ruptura de frío no resueltas en toda la flota, con o sin carga. */
    public function totalAlertasAbiertas(): int
    {
        return Alerta::where('tipo', 'ruptura_frio')->where('estado', '<>', 'resuelta')->count();
    }

    /**
     * Alertas de ruptura de frío no resueltas con los pedidos y clientes que
     * afectan, dentro de la cartera del usuario. Cruce (DATA_MODEL.md
     * `alerta`): alerta → lectura → despacho del camión que estaba en ruta a
     * esa hora → paradas aún no entregadas a esa hora → pedido → dirección →
     * cliente. Mismo criterio que la vista `bi_pedido_afectado_alerta`.
     *
     * Para el vendedor solo aparecen las alertas que tocan algún cliente de
     * su cartera, y solo esos clientes.
     *
     * @return Collection<int, array{alerta: Alerta, camion_id: string, temp_camara: float, fecha_hora: CarbonInterface, afectados: Collection<int, object>}>
     */
    public function alertasAbiertas(Usuario $usuario): Collection
    {
        $clientesVisibles = Cliente::visiblePara($usuario)->select('cliente.id_cliente');

        $afectados = DB::table('alerta')
            ->join('telemetria as t', 't.id_telemetria', '=', 'alerta.id_telemetria')
            ->join('despacho as d', function (JoinClause $j) {
                $j->on('d.camion_id', '=', 't.camion_id')
                    ->whereIn('d.estado', ['en_ruta', 'finalizado'])
                    ->whereRaw("coalesce(d.salida_real, '-infinity') <= t.fecha_hora")
                    ->whereRaw('(d.llegada_real IS NULL OR d.llegada_real >= t.fecha_hora)');
            })
            ->join('despacho_pedido as dp', function (JoinClause $j) {
                $j->on('dp.id_despacho', '=', 'd.id_despacho')
                    ->whereRaw('(dp.fecha_entrega IS NULL OR dp.fecha_entrega >= t.fecha_hora)');
            })
            ->join('pedido as p', 'p.id_pedido', '=', 'dp.id_pedido')
            ->join('direccion_despacho as dir', 'dir.id_direccion', '=', 'p.id_direccion')
            ->join('cliente as c', 'c.id_cliente', '=', 'dir.id_cliente')
            ->where('alerta.tipo', 'ruptura_frio')
            ->where('alerta.estado', '<>', 'resuelta')
            ->whereIn('c.id_cliente', $clientesVisibles)
            ->orderBy('dp.orden_parada')
            ->get(['alerta.id_alerta', 'd.id_despacho', 'dp.orden_parada', 'dp.estado_entrega', 'p.id_pedido', 'c.id_cliente', 'c.nombre_razon_social'])
            ->groupBy('id_alerta');

        if ($afectados->isEmpty()) {
            return collect();
        }

        return Alerta::whereIn('id_alerta', $afectados->keys())
            ->with('telemetria')
            ->get()
            ->sortByDesc(fn (Alerta $a) => $a->telemetria->fecha_hora)
            ->values()
            ->map(fn (Alerta $a) => [
                'alerta' => $a,
                'camion_id' => $a->telemetria->camion_id,
                'temp_camara' => (float) $a->telemetria->temp_camara,
                'fecha_hora' => $a->telemetria->fecha_hora,
                'afectados' => $afectados[$a->id_alerta]->values(),
            ]);
    }
}
