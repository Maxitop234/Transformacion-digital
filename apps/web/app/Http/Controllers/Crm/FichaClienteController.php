<?php

namespace App\Http\Controllers\Crm;

use App\Domain\Clientes\AgregarContacto;
use App\Domain\Crm\RegistrarInteraccion;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Contactos e interacciones registrados desde la ficha del cliente (T-014). */
class FichaClienteController extends Controller
{
    public function contacto(Request $request, int $cliente, AgregarContacto $agregar): RedirectResponse
    {
        $cliente = ClienteController::visibleOFalla($request, $cliente);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'cargo' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'es_principal' => ['nullable', 'boolean'],
        ]);

        $agregar($cliente, $datos);

        return redirect()->route('crm.clientes.show', $cliente->id_cliente)->with('estado', 'Contacto agregado.');
    }

    public function interaccion(Request $request, int $cliente, RegistrarInteraccion $registrar): RedirectResponse
    {
        $cliente = ClienteController::visibleOFalla($request, $cliente);

        $datos = $request->validate([
            'tipo' => ['required', Rule::in(RegistrarInteraccion::TIPOS)],
            'resumen' => ['required', 'string', 'max:2000'],
            'id_oportunidad' => ['nullable', 'integer'],
        ]);

        $registrar($cliente, $request->user(), $datos['tipo'], $datos['resumen'], isset($datos['id_oportunidad']) ? (int) $datos['id_oportunidad'] : null);

        return redirect()->route('crm.clientes.show', $cliente->id_cliente)->with('estado', 'Interacción registrada.');
    }
}
