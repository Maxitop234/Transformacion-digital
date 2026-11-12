<?php

namespace App\Http\Controllers\Crm;

use App\Domain\Crm\GuardarOportunidad;
use App\Domain\Crm\TransicionesOportunidad;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\EtapaPipeline;
use App\Models\HistorialEtapa;
use App\Models\Oportunidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pipeline de oportunidades (T-015, API.md §1.4, gate `oportunidades`:
 * vendedor y administrador). Todo con `visiblePara()` (RN-10): el vendedor
 * ve y mueve solo las oportunidades de su cartera; una ajena responde 404.
 */
class OportunidadController extends Controller
{
    /** Días que una oportunidad cerrada sigue visible en su columna. */
    private const DIAS_CERRADAS = 30;

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $etapas = EtapaPipeline::orderBy('orden')->get();

        $oportunidades = Oportunidad::visiblePara($usuario)
            ->with(['cliente:id_cliente,nombre_razon_social', 'vendedor:id_vendedor,nombre'])
            ->addSelect(['en_etapa_desde' => HistorialEtapa::selectRaw('max(fecha_hora)')
                ->whereColumn('historial_etapa.id_oportunidad', 'oportunidad.id_oportunidad')
                ->whereColumn('historial_etapa.id_etapa', 'oportunidad.id_etapa')])
            ->where(fn (Builder $q) => $q->whereNull('fecha_cierre')->orWhere('fecha_cierre', '>=', now()->subDays(self::DIAS_CERRADAS)))
            ->orderBy('fecha_creacion')
            ->get()
            ->groupBy('id_etapa');

        return view('crm.oportunidades.index', [
            'etapas' => $etapas,
            'oportunidades' => $oportunidades,
            'clientes' => Cliente::visiblePara($usuario)->where('estado_cliente', '<>', 'inactivo')->orderBy('nombre_razon_social')->get(['id_cliente', 'nombre_razon_social']),
            'origenes' => GuardarOportunidad::ORIGENES_MANUALES,
            'diasCerradas' => self::DIAS_CERRADAS,
        ]);
    }

    public function store(Request $request, GuardarOportunidad $guardar): RedirectResponse
    {
        $datos = $request->validate([
            'id_cliente' => ['required', 'integer'],
            'origen' => ['required', Rule::in(GuardarOportunidad::ORIGENES_MANUALES)],
            ...$this->reglasEdicion(),
        ], [], $this->atributos());

        $cliente = Cliente::visiblePara($request->user())->findOrFail($datos['id_cliente']);
        $oportunidad = $guardar->crear($cliente, $request->user(), $datos);

        return redirect()->route('crm.oportunidades.show', $oportunidad->id_oportunidad)->with('estado', 'Oportunidad creada en «Prospecto nuevo».');
    }

    public function show(Request $request, int $oportunidad, TransicionesOportunidad $transiciones): View
    {
        $oportunidad = $this->oportunidad($request, $oportunidad);
        $oportunidad->load([
            'cliente.tipoCliente', 'vendedor', 'etapaPipeline', 'pedidos.estadoPedido',
            'historialesEtapa' => fn ($q) => $q->with(['etapaPipeline', 'usuario.vendedor'])->orderBy('fecha_hora')->orderBy('id_historial'),
            'interacciones' => fn ($q) => $q->orderByDesc('fecha_hora')->limit(10),
        ]);

        return view('crm.oportunidades.show', [
            'oportunidad' => $oportunidad,
            'destinos' => $transiciones->disponibles($oportunidad),
            'pedidosVinculables' => TransicionesOportunidad::pedidosVinculables($oportunidad)->orderByDesc('fecha_pedido')->limit(20)->get(['id_pedido', 'fecha_pedido']),
            'puedeDeshacer' => $transiciones->puedeDeshacer($oportunidad),
        ]);
    }

    public function update(Request $request, int $oportunidad, GuardarOportunidad $guardar): RedirectResponse
    {
        $oportunidad = $this->oportunidad($request, $oportunidad);
        $datos = $request->validate($this->reglasEdicion(), [], $this->atributos());

        $guardar->editar($oportunidad, $datos);

        return redirect()->route('crm.oportunidades.show', $oportunidad->id_oportunidad)->with('estado', 'Oportunidad actualizada.');
    }

    public function etapa(Request $request, int $oportunidad, TransicionesOportunidad $transiciones): RedirectResponse
    {
        $oportunidad = $this->oportunidad($request, $oportunidad);
        $datos = $request->validate([
            'etapa' => ['required', Rule::exists('etapa_pipeline', 'nombre')],
            'motivo' => ['nullable', 'string', 'max:200'],
            'comentario' => ['nullable', 'string', 'max:250'],
            'id_pedido' => ['nullable', 'integer'],
        ]);

        $transiciones->cambiar($oportunidad, $datos['etapa'], $request->user(), $datos);

        return redirect()->route('crm.oportunidades.show', $oportunidad->id_oportunidad)->with('estado', "La oportunidad pasó a «{$datos['etapa']}».");
    }

    public function deshacer(Request $request, int $oportunidad, TransicionesOportunidad $transiciones): RedirectResponse
    {
        $oportunidad = $this->oportunidad($request, $oportunidad);
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:200']]);

        $oportunidad = $transiciones->deshacerVinculo($oportunidad, $request->user(), $datos['motivo']);

        return redirect()->route('crm.oportunidades.show', $oportunidad->id_oportunidad)
            ->with('estado', "Vínculo deshecho: la oportunidad volvió a «{$oportunidad->etapaPipeline()->value('nombre')}».");
    }

    private function oportunidad(Request $request, int $id): Oportunidad
    {
        return Oportunidad::visiblePara($request->user())->findOrFail($id);
    }

    /** @return array<string, list<mixed>> */
    private function reglasEdicion(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:150'],
            'monto_estimado' => ['nullable', 'numeric', 'min:1', 'max:999999999999'],
            'fecha_cierre_estimada' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    private function atributos(): array
    {
        return [
            'id_cliente' => 'cliente',
            'titulo' => 'título',
            'monto_estimado' => 'monto estimado',
            'fecha_cierre_estimada' => 'fecha estimada de cierre',
        ];
    }
}
