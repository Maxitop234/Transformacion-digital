<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Catalogo\PerfilPrecio;
use App\Domain\Catalogo\Precios;
use App\Domain\Ventas\Carritos;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Carrito del portal (API.md §1.3). Cada cliente opera solo sobre su propio
 * carrito: no hay identificador de carrito en la URL, se resuelve siempre
 * desde la sesión. La lógica vive en `App\Domain\Ventas\Carritos`.
 */
class CarritoController extends Controller
{
    private const REGLAS_CANTIDAD = ['numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'];

    public function __construct(private readonly Precios $precios, private readonly Carritos $carritos) {}

    public function show(Request $request): View
    {
        $perfil = $this->perfil($request);

        return view('portal.carrito.show', [
            'perfil' => $perfil,
            'resumen' => $this->carritos->resumen($perfil),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'sku' => ['required', 'string', 'max:30'],
            'cantidad' => ['nullable', ...self::REGLAS_CANTIDAD],
        ]);

        $aviso = $this->carritos->agregar($this->perfil($request), $datos['sku'], (string) ($datos['cantidad'] ?? '1'));

        return back()
            ->with('estado', 'Producto agregado al carrito.')
            ->with('aviso', $aviso);
    }

    public function update(Request $request, string $sku): RedirectResponse
    {
        $datos = $request->validate(['cantidad' => ['required', ...self::REGLAS_CANTIDAD]]);

        $aviso = $this->carritos->cambiarCantidad($this->perfil($request), $sku, (string) $datos['cantidad']);

        return redirect()->route('portal.carrito')
            ->with('estado', 'Cantidad actualizada.')
            ->with('aviso', $aviso);
    }

    public function destroy(Request $request, string $sku): RedirectResponse
    {
        $this->carritos->quitar($this->perfil($request)->cliente, $sku);

        return redirect()->route('portal.carrito')->with('estado', 'Producto quitado del carrito.');
    }

    /** Solo cuentas con ficha de cliente tienen carrito. */
    private function perfil(Request $request): PerfilPrecio
    {
        $perfil = $this->precios->perfil($request->user());
        abort_if($perfil->cliente === null, 403);

        return $perfil;
    }
}
