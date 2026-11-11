<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Catalogo\Precios;
use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\RangoConservacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo público del portal (API.md §1.2). El precio depende de quién mira
 * (`Precios::perfil()`); los productos inactivos no existen para el portal.
 * Disponible para venta = stock_disponible − stock_reservado (RN-04).
 */
class CatalogoController extends Controller
{
    public function index(Request $request, Precios $precios): View
    {
        $filtros = $request->validate([
            'categoria' => ['nullable', 'integer'],
            'rango' => ['nullable', 'integer'],
            'disponible' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $productos = Producto::where('activo', true)
            ->with(['categoria', 'rangoConservacion', 'inventario'])
            ->when($filtros['categoria'] ?? null, fn (Builder $q, $id) => $q->where('id_categoria', $id))
            ->when($filtros['rango'] ?? null, fn (Builder $q, $id) => $q->where('id_rango', $id))
            ->when($request->boolean('disponible'), fn (Builder $q) => $q->whereHas(
                'inventario', fn (Builder $q) => $q->whereColumn('stock_disponible', '>', 'stock_reservado'),
            ))
            ->when(trim($filtros['q'] ?? ''), fn (Builder $q, $texto) => $q->where(fn (Builder $q) => $q
                ->where('nombre', 'ilike', '%'.addcslashes($texto, '%_\\').'%')
                ->orWhere('sku', 'ilike', '%'.addcslashes($texto, '%_\\').'%')))
            ->orderBy('nombre')
            ->paginate(24)
            ->withQueryString();

        $perfil = $precios->perfil($request->user());

        return view('portal.catalogo.index', [
            'productos' => $productos,
            'perfil' => $perfil,
            'preciosNetos' => $precios->precios($productos->pluck('id_producto'), $perfil->lista),
            'categorias' => Categoria::orderBy('nombre')->get(),
            'rangos' => RangoConservacion::orderBy('temp_min_c')->get(),
        ]);
    }

    public function show(Request $request, string $sku, Precios $precios): View
    {
        $producto = Producto::where('activo', true)
            ->with(['categoria', 'rangoConservacion', 'inventario'])
            ->where('sku', $sku)
            ->firstOrFail();

        $perfil = $precios->perfil($request->user());

        return view('portal.catalogo.show', [
            'producto' => $producto,
            'perfil' => $perfil,
            'precioNeto' => $precios->precio($producto, $perfil->lista),
        ]);
    }
}
