<?php

namespace App\Domain\Crm;

use App\Models\Oportunidad;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Builder;

/**
 * BUSINESS_RULES.md §6: un pedido B2B de un cliente con una oportunidad en
 * «Propuesta» o «Negociación» se vincula a ella y la oportunidad pasa a
 * «Ganado» (usuario `sistema`, a través de `TransicionesOportunidad`). Una
 * recompra sin oportunidad abierta no crea ninguna, para no inflar la
 * conversión. Se llama dentro de la transacción que confirma el pedido. El
 * vendedor puede deshacer el vínculo (`TransicionesOportunidad::deshacerVinculo`).
 */
final class GanarOportunidadConPedido
{
    public const ETAPAS_QUE_SE_GANAN = [TransicionesOportunidad::PROPUESTA, TransicionesOportunidad::NEGOCIACION];

    public function __construct(private readonly TransicionesOportunidad $transiciones) {}

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

        return $this->transiciones->ganarConPedido($oportunidad, $pedido, null);
    }
}
