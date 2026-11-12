<?php

namespace App\Domain\Crm;

use App\Models\Cliente;
use App\Models\EtapaPipeline;
use App\Models\HistorialEtapa;
use App\Models\Oportunidad;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Alta y edición de oportunidades en el CRM (T-015, BUSINESS_RULES.md §5).
 *
 * - Nace en «Prospecto nuevo» con su fila de `historial_etapa`, asignada al
 *   vendedor que la crea (el administrador: al vendedor del cliente).
 * - Editar cambia título, monto y fecha estimada de cierre, solo mientras
 *   está abierta. La etapa se cambia únicamente con `TransicionesOportunidad`.
 */
final class GuardarOportunidad
{
    /** Orígenes que se ingresan a mano; `portal` lo usa solo el registro web (T-009). */
    public const ORIGENES_MANUALES = ['vendedor', 'telefono', 'referido'];

    /** @param  array{titulo: string, origen: string, monto_estimado?: string|null, fecha_cierre_estimada?: string|null}  $datos */
    public function crear(Cliente $cliente, Usuario $actor, array $datos): Oportunidad
    {
        if ($cliente->estado_cliente === 'inactivo') {
            throw ValidationException::withMessages(['id_cliente' => 'El cliente está inactivo.']);
        }

        $idVendedor = $actor->vendedor?->id_vendedor ?? $cliente->id_vendedor;

        if ($idVendedor === null) {
            throw ValidationException::withMessages(['id_cliente' => 'Asigna un vendedor al cliente antes de abrirle una oportunidad.']);
        }

        return DB::transaction(function () use ($cliente, $actor, $datos, $idVendedor) {
            $etapa = EtapaPipeline::where('nombre', TransicionesOportunidad::PROSPECTO)->firstOrFail();

            $oportunidad = Oportunidad::create([
                'id_cliente' => $cliente->id_cliente,
                'id_vendedor' => $idVendedor,
                'id_etapa' => $etapa->id_etapa,
                'titulo' => trim($datos['titulo']),
                'origen' => $datos['origen'],
                'monto_estimado' => $datos['monto_estimado'] ?? null,
                'fecha_cierre_estimada' => $datos['fecha_cierre_estimada'] ?? null,
                'fecha_creacion' => now(),
            ]);

            HistorialEtapa::create([
                'id_oportunidad' => $oportunidad->id_oportunidad,
                'id_etapa' => $etapa->id_etapa,
                'id_usuario' => $actor->id_usuario,
                'fecha_hora' => now(),
                'comentario' => 'Oportunidad creada en el CRM',
            ]);

            return $oportunidad;
        });
    }

    /** @param  array{titulo: string, monto_estimado?: string|null, fecha_cierre_estimada?: string|null}  $datos */
    public function editar(Oportunidad $oportunidad, array $datos): Oportunidad
    {
        return DB::transaction(function () use ($oportunidad, $datos) {
            $oportunidad = Oportunidad::whereKey($oportunidad->id_oportunidad)->with('etapaPipeline')->lockForUpdate()->firstOrFail();

            if ($oportunidad->etapaPipeline->es_cierre) {
                throw new HttpException(409, 'Una oportunidad cerrada no se edita.');
            }

            $oportunidad->update([
                'titulo' => trim($datos['titulo']),
                'monto_estimado' => $datos['monto_estimado'] ?? null,
                'fecha_cierre_estimada' => $datos['fecha_cierre_estimada'] ?? null,
            ]);

            return $oportunidad;
        });
    }
}
