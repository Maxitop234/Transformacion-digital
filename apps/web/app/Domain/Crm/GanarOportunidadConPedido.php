<?php

namespace App\Domain\Crm;

use App\Domain\Ventas\TransicionesPedido;
use App\Models\EtapaPipeline;
use App\Models\HistorialEtapa;
use App\Models\Oportunidad;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Builder;

/**
 * BUSINESS_RULES.md §6: un pedido B2B de un cliente con una oportunidad en
 * «Propuesta» o «Negociación» se vincula a ella y la oportunidad pasa a
 * «Ganado» (usuario `sistema`, con su fila de `historial_etapa`). Una
 * recompra sin oportunidad abierta no crea ninguna, para no inflar la
 * conversión. Se llama dentro de la transacción que confirma el pedido.
 */
final class GanarOportunidadConPedido
{
    public const ETAPAS_QUE_SE_GANAN = ['Propuesta', 'Negociación'];

    public function __invoke(Pedido $pedido, int $idCliente): ?Oportunidad
    {
        if ($pedido->id_oportunidad !== null) {
            return null;
        }

        $oportunidad = Oportunidad::where('id_cliente', $idCliente)
            ->whereHas('etapaPipeline', fn (Builder $q) => $q->whereIn('nombre', self::ETAPAS_QUE_SE_GANAN))
            ->orderBy('fecha_creacion')
            ->lockForUpdate()
            ->first();

        if ($oportunidad === null) {
            return null;
        }

        $ganado = EtapaPipeline::where('nombre', 'Ganado')->firstOrFail();

        $oportunidad->update(['id_etapa' => $ganado->id_etapa, 'fecha_cierre' => now()]);
        $pedido->update(['id_oportunidad' => $oportunidad->id_oportunidad]);

        HistorialEtapa::create([
            'id_oportunidad' => $oportunidad->id_oportunidad,
            'id_etapa' => $ganado->id_etapa,
            'id_usuario' => TransicionesPedido::idUsuarioSistema(),
            'fecha_hora' => now(),
            'comentario' => "Ganada con el pedido N.º {$pedido->id_pedido}",
        ]);

        return $oportunidad;
    }
}
