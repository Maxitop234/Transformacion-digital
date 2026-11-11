<?php

namespace App\Http\Controllers\Crm\Admin;

use App\Domain\Catalogo\RegistrarMovimientoInventario;
use App\Http\Controllers\Controller;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Stock por producto, ingresos y ajustes (cada uno deja su movimiento). */
class InventarioController extends Controller
{
    public function index(): View
    {
        return view('crm.admin.inventario.index', [
            'productos' => Producto::with('inventario')->orderBy('nombre')->get(),
            'movimientos' => MovimientoInventario::with(['producto', 'usuario'])
                ->orderByDesc('fecha_hora')->orderByDesc('id_movimiento')->limit(30)->get(),
            'tipos' => RegistrarMovimientoInventario::TIPOS_MANUALES,
        ]);
    }

    public function store(Request $request, RegistrarMovimientoInventario $registrar): RedirectResponse
    {
        $datos = $request->validate([
            'id_producto' => ['required', 'integer', Rule::exists('producto', 'id_producto')],
            'tipo' => ['required', Rule::in(RegistrarMovimientoInventario::TIPOS_MANUALES)],
            'cantidad' => ['required', 'numeric', 'decimal:0,2', 'between:-9999999999.99,9999999999.99'],
        ], [], ['id_producto' => 'producto']);

        $registrar((int) $datos['id_producto'], $datos['tipo'], (string) $datos['cantidad'], $request->user());

        return redirect()->route('crm.admin.inventario.index')->with('estado', 'Movimiento registrado.');
    }
}
