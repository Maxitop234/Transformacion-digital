<?php

namespace App\Http\Controllers\Crm\Admin;

use App\Domain\Catalogo\ActualizarPreciosLista;
use App\Http\Controllers\Controller;
use App\Models\DetalleListaPrecio;
use App\Models\ListaPrecio;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Listas de precios (B2C «Público general» e institucionales) y sus precios. */
class ListaPrecioController extends Controller
{
    public function index(): View
    {
        $listas = ListaPrecio::withCount(['clientes', 'detallesListaPrecio'])->orderBy('nombre')->get();

        return view('crm.admin.listas.index', ['listas' => $listas]);
    }

    public function create(): View
    {
        return view('crm.admin.listas.form', ['lista' => new ListaPrecio(['activa' => true, 'vigente_desde' => now()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $lista = ListaPrecio::create($this->validar($request));

        return redirect()->route('crm.admin.listas-precio.edit', $lista)->with('estado', 'Lista creada. Ahora fija sus precios.');
    }

    public function edit(ListaPrecio $lista): View
    {
        $precios = DetalleListaPrecio::where('id_lista_precio', $lista->id_lista_precio)
            ->pluck('precio_unitario', 'id_producto');

        return view('crm.admin.listas.form', [
            'lista' => $lista,
            'productos' => Producto::with('categoria')->orderBy('nombre')->get(),
            'precios' => $precios,
        ]);
    }

    public function update(Request $request, ListaPrecio $lista): RedirectResponse
    {
        $lista->update($this->validar($request, $lista));

        return redirect()->route('crm.admin.listas-precio.edit', $lista)->with('estado', 'Lista actualizada.');
    }

    public function precios(Request $request, ListaPrecio $lista, ActualizarPreciosLista $actualizar): RedirectResponse
    {
        $datos = $request->validate([
            'precios' => ['required', 'array'],
            'precios.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ], [], ['precios.*' => 'precio']);

        $validos = Producto::whereIn('id_producto', array_keys($datos['precios']))->pluck('id_producto')->all();
        $actualizar($lista, array_intersect_key($datos['precios'], array_flip($validos)));

        return redirect()->route('crm.admin.listas-precio.edit', $lista)->with('estado', 'Precios guardados.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?ListaPrecio $lista = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80', Rule::unique('lista_precio', 'nombre')->ignore($lista?->id_lista_precio, 'id_lista_precio')],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'activa' => ['sometimes', 'boolean'],
        ], [], ['vigente_desde' => 'vigente desde', 'vigente_hasta' => 'vigente hasta']);

        return $datos + ['activa' => $request->boolean('activa'), 'vigente_hasta' => null];
    }
}
