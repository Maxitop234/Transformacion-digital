<?php

namespace App\Http\Controllers\Crm;

use App\Domain\Logistica\AfectadosPorAlerta;
use App\Domain\Logistica\ResolverAlerta;
use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Models\Rol;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Alertas de frío (T-017, gate `alertas`). El vendedor ve solo las que
 * afectan a clientes de su cartera, y solo esos clientes (RN-10); el
 * despachador y el administrador ven todas y son quienes las resuelven.
 */
class AlertaController extends Controller
{
    public function index(Request $request, AfectadosPorAlerta $afectados): View
    {
        $usuario = $request->user();
        $filtros = $request->validate(['estado' => ['nullable', Rule::in(['abiertas', 'resueltas', 'todas'])]]);
        $estado = $filtros['estado'] ?? 'abiertas';
        $esVendedor = $usuario->tieneRol(Rol::VENDEDOR);

        $alertas = Alerta::query()
            ->with(['telemetria', 'usuarioResuelve'])
            ->when($estado === 'abiertas', fn (Builder $q) => $q->where('estado', '<>', 'resuelta'))
            ->when($estado === 'resueltas', fn (Builder $q) => $q->where('estado', 'resuelta'))
            ->when($esVendedor, fn (Builder $q) => $q->whereIn('id_alerta', $afectados->idsVisibles($usuario)))
            ->orderByDesc('id_alerta')
            ->paginate(20)
            ->withQueryString();

        return view('crm.alertas.index', [
            'alertas' => $alertas,
            'afectados' => $afectados->porAlerta($esVendedor ? $usuario : null, $alertas->pluck('id_alerta')),
            'estado' => $estado,
            'puedeResolver' => Gate::allows('despachos'),
        ]);
    }

    public function resolver(Request $request, int $alerta, ResolverAlerta $resolver): RedirectResponse
    {
        abort_unless(Gate::allows('despachos'), 403);
        $datos = $request->validate(['accion_correctiva' => ['required', 'string', 'max:300']], [], ['accion_correctiva' => 'acción correctiva']);

        $resolver(Alerta::findOrFail($alerta), $datos['accion_correctiva'], $request->user());

        return redirect()->route('crm.alertas', $request->only('estado'))->with('estado', "Alerta {$alerta} resuelta.");
    }
}
