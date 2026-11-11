<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Catalogo\PerfilPrecio;
use App\Domain\Catalogo\Precios;
use App\Domain\Clientes\PuntosEntrega;
use App\Http\Controllers\Controller;
use App\Models\Comuna;
use App\Models\DireccionDespacho;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Puntos de entrega del cliente (T-012, `/mis-direcciones`, RN-06). Todos
 * los ven; solo una cuenta B2B agrega o desactiva puntos. Un punto ajeno
 * responde 404 (`visiblePara`).
 */
class DireccionController extends Controller
{
    public function index(Request $request, Precios $precios): View
    {
        $perfil = $precios->perfil($request->user());

        return view('portal.direcciones.index', [
            'perfil' => $perfil,
            'direcciones' => DireccionDespacho::visiblePara($request->user())
                ->with('comuna')->orderByDesc('activa')->orderBy('alias')->get(),
            'comunas' => $perfil->esB2B ? Comuna::orderBy('nombre')->get(['id_comuna', 'nombre']) : collect(),
        ]);
    }

    public function store(Request $request, Precios $precios, PuntosEntrega $puntos): RedirectResponse
    {
        $perfil = $this->soloEmpresa($request, $precios);
        $datos = $request->validate([
            'alias' => ['required', 'string', 'max:60'],
            'calle' => ['required', 'string', 'max:150'],
            'id_comuna' => ['required', 'integer', Rule::exists('comuna', 'id_comuna')],
            'referencia' => ['nullable', 'string', 'max:200'],
            'ventana_inicio' => ['nullable', 'date_format:H:i', 'required_with:ventana_fin'],
            'ventana_fin' => ['nullable', 'date_format:H:i', 'required_with:ventana_inicio', 'after:ventana_inicio'],
        ], [], [
            'id_comuna' => 'comuna',
            'ventana_inicio' => 'inicio de la ventana',
            'ventana_fin' => 'fin de la ventana',
        ]);

        $direccion = $puntos->agregar($perfil->cliente, $datos);

        return redirect()->route('portal.direcciones')->with('estado', "Agregamos el punto de entrega «{$direccion->alias}».");
    }

    public function update(Request $request, int $direccion, Precios $precios, PuntosEntrega $puntos): RedirectResponse
    {
        $this->soloEmpresa($request, $precios);
        $direccion = DireccionDespacho::visiblePara($request->user())->findOrFail($direccion);
        $datos = $request->validate(['activa' => ['required', 'boolean']]);

        $puntos->cambiarActiva($direccion, (bool) $datos['activa']);

        return redirect()->route('portal.direcciones')->with('estado', $direccion->activa
            ? "«{$direccion->alias}» vuelve a ofrecerse en el checkout."
            : "«{$direccion->alias}» quedó inactivo: ya no se ofrece en el checkout.");
    }

    private function soloEmpresa(Request $request, Precios $precios): PerfilPrecio
    {
        $perfil = $precios->perfil($request->user());
        abort_unless($perfil->esB2B && $perfil->cliente !== null, 403, 'Solo las cuentas de empresa gestionan varios puntos de entrega.');

        return $perfil;
    }
}
