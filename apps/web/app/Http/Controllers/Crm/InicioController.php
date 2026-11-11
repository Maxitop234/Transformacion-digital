<?php

namespace App\Http\Controllers\Crm;

use App\Domain\Crm\PanelCrm;
use App\Http\Controllers\Controller;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Panel de inicio del CRM (T-013). Los bloques comerciales solo se calculan
 * para quien tiene el área `clientes` (vendedor y administrador); las
 * alertas, para quien tiene `alertas` (los tres roles del personal).
 */
class InicioController extends Controller
{
    public function __invoke(Request $request, PanelCrm $panel): View
    {
        $usuario = $request->user();
        $comercial = Gate::allows('clientes');

        return view('crm.inicio', [
            'comercial' => $comercial,
            'indicadores' => $comercial ? $panel->indicadores($usuario) : null,
            'pendientes' => $comercial ? $panel->empresasPendientes($usuario) : collect(),
            'sinActividad' => $comercial ? $panel->oportunidadesSinActividad($usuario) : collect(),
            'alertas' => Gate::allows('alertas') ? $panel->alertasAbiertas($usuario) : collect(),
            'diasSinActividad' => config('negocio.oportunidad_sin_actividad_dias'),
            // Total de la flota: solo para quien ve todas las alertas (no el vendedor, RN-10).
            'alertasAbiertasTotal' => $usuario->tieneRol(Rol::ADMINISTRADOR, Rol::DESPACHADOR) ? $panel->totalAlertasAbiertas() : null,
        ]);
    }
}
