<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Flota (T-017, gate `flota`): última lectura de cada camión, su despacho en
 * ruta y si tiene una ruptura de frío abierta. Usa el índice
 * `(camion_id, fecha_hora DESC)` de `telemetria` (ADR-0017).
 */
class FlotaController extends Controller
{
    public function __invoke(): View
    {
        $camiones = DB::table('camion as c')
            ->leftJoinLateral(
                DB::table('telemetria as t')->whereColumn('t.camion_id', 'c.camion_id')->orderByDesc('t.fecha_hora')->limit(1),
                'ultima',
            )
            ->leftJoin('despacho as d', fn ($j) => $j->on('d.camion_id', '=', 'c.camion_id')->where('d.estado', 'en_ruta'))
            ->selectRaw('c.camion_id, c.patente, c.activo, ultima.fecha_hora, ultima.temp_camara, ultima.lat, ultima."long", ultima.combustible_pct, ultima.alerta_ruptura_frio, d.id_despacho')
            ->selectRaw("EXISTS (SELECT 1 FROM alerta a JOIN telemetria t2 ON t2.id_telemetria = a.id_telemetria WHERE t2.camion_id = c.camion_id AND a.tipo = 'ruptura_frio' AND a.estado <> 'resuelta') AS alerta_abierta")
            ->orderBy('c.camion_id')
            ->get();

        return view('crm.flota.index', ['camiones' => $camiones]);
    }
}
