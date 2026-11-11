<?php

namespace App\Domain\Logistica;

use App\Models\Cliente;
use App\Models\Usuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos y clientes afectados por cada alerta de ruptura de frío
 * (DATA_MODEL.md `alerta`, BUSINESS_RULES.md §7): alerta → lectura →
 * despacho del camión que estaba en ruta a esa hora → paradas aún no
 * entregadas a esa hora → pedido → dirección → cliente. Mismo criterio que la
 * vista `bi_pedido_afectado_alerta` (T-020).
 *
 * Con `$usuario` se limita a los clientes de su cartera (RN-10).
 */
final class AfectadosPorAlerta
{
    /**
     * @param  iterable<int>|null  $idsAlerta  null: todas las alertas
     * @return Collection<int, Collection<int, object>> id_alerta => filas
     */
    public function porAlerta(?Usuario $usuario = null, ?iterable $idsAlerta = null, bool $soloAbiertas = false): Collection
    {
        return $this->consulta($usuario, $idsAlerta, $soloAbiertas)
            ->orderBy('dp.orden_parada')
            ->get(['alerta.id_alerta', 'd.id_despacho', 'dp.orden_parada', 'dp.estado_entrega', 'p.id_pedido', 'c.id_cliente', 'c.nombre_razon_social', 'c.id_vendedor'])
            ->groupBy('id_alerta');
    }

    /**
     * Rupturas de frío que tocaron al pedido en cada despacho (seguimiento del
     * portal, T-012): mismo cruce, filtrado por pedido. Sin datos del conductor.
     *
     * @return Collection<int, Collection<int, object>> id_despacho => alertas
     */
    public function delPedido(int $idPedido): Collection
    {
        return $this->consulta(null, null, false)
            ->where('p.id_pedido', $idPedido)
            ->orderBy('t.fecha_hora')
            ->get(['d.id_despacho', 'alerta.id_alerta', 'alerta.estado', 't.fecha_hora', 't.temp_camara'])
            ->groupBy('id_despacho');
    }

    /** Subconsulta con los `id_alerta` que afectan a algún cliente visible para el usuario. */
    public function idsVisibles(Usuario $usuario): Builder
    {
        return $this->consulta($usuario, null, false)->select('alerta.id_alerta')->distinct();
    }

    private function consulta(?Usuario $usuario, ?iterable $idsAlerta, bool $soloAbiertas): Builder
    {
        return DB::table('alerta')
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
            ->when($soloAbiertas, fn (Builder $q) => $q->where('alerta.estado', '<>', 'resuelta'))
            ->when($idsAlerta !== null, fn (Builder $q) => $q->whereIn('alerta.id_alerta', collect($idsAlerta)->all()))
            ->when($usuario !== null, fn (Builder $q) => $q->whereIn('c.id_cliente', Cliente::visiblePara($usuario)->select('cliente.id_cliente')));
    }
}
