<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cartera de clientes del CRM. El vendedor solo ve la suya (RN-10): un
 * cliente ajeno no se encuentra y responde 404. La ficha completa se
 * construye en T-014.
 */
class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Cliente::class);

        $clientes = Cliente::visiblePara($request->user())
            ->with(['tipoCliente', 'vendedor'])
            ->orderBy('nombre_razon_social')
            ->paginate(25);

        return view('crm.clientes.index', ['clientes' => $clientes]);
    }

    public function show(Request $request, int $cliente): View
    {
        $cliente = Cliente::visiblePara($request->user())
            ->with(['tipoCliente', 'vendedor', 'listaPrecio'])
            ->findOrFail($cliente);

        $this->authorize('view', $cliente);

        return view('crm.clientes.show', ['cliente' => $cliente]);
    }
}
