<?php

namespace App\Http\Controllers\Crm\Admin;

use App\Domain\Catalogo\GuardarProducto;
use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\RangoConservacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Mantenedor de productos: categoría, rango de conservación y activo. */
class ProductoController extends Controller
{
    /** Valores del CHECK `producto_unidad_medida_check`. */
    public const UNIDADES = ['kg', 'un', 'caja'];

    public function index(Request $request): View
    {
        $productos = Producto::with(['categoria', 'rangoConservacion'])
            ->when($request->string('q')->trim()->value(), fn ($q, $texto) => $q->where(fn ($q) => $q
                ->where('nombre', 'ilike', "%{$texto}%")->orWhere('sku', 'ilike', "%{$texto}%")))
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return view('crm.admin.productos.index', ['productos' => $productos]);
    }

    public function create(): View
    {
        return view('crm.admin.productos.form', $this->opciones() + ['producto' => new Producto(['activo' => true])]);
    }

    public function store(Request $request, GuardarProducto $guardar): RedirectResponse
    {
        $producto = $guardar->crear($this->validar($request));

        return redirect()->route('crm.admin.productos.index')->with('estado', "Producto {$producto->sku} creado.");
    }

    public function edit(Producto $producto): View
    {
        return view('crm.admin.productos.form', $this->opciones() + ['producto' => $producto]);
    }

    public function update(Request $request, Producto $producto, GuardarProducto $guardar): RedirectResponse
    {
        $guardar->actualizar($producto, $this->validar($request, $producto));

        return redirect()->route('crm.admin.productos.index')->with('estado', "Producto {$producto->sku} actualizado.");
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Producto $producto = null): array
    {
        $datos = $request->validate([
            'sku' => ['required', 'string', 'max:30', Rule::unique('producto', 'sku')->ignore($producto?->id_producto, 'id_producto')],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'id_categoria' => ['required', 'integer', Rule::exists('categoria', 'id_categoria')],
            'id_rango' => ['required', 'integer', Rule::exists('rango_conservacion', 'id_rango')],
            'unidad_medida' => ['required', Rule::in(self::UNIDADES)],
            'activo' => ['sometimes', 'boolean'],
        ], [], [
            'id_categoria' => 'categoría',
            'id_rango' => 'rango de conservación',
            'unidad_medida' => 'unidad de medida',
        ]);

        return $datos + ['activo' => $request->boolean('activo')];
    }

    /** @return array<string, mixed> */
    private function opciones(): array
    {
        return [
            'categorias' => Categoria::orderBy('nombre')->get(),
            'rangos' => RangoConservacion::orderBy('temp_min_c')->get(),
            'unidades' => self::UNIDADES,
        ];
    }
}
