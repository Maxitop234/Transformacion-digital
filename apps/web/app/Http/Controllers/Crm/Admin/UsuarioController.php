<?php

namespace App\Http\Controllers\Crm\Admin;

use App\Domain\Clientes\AdministrarUsuarios;
use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Vendedor;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Usuarios: alta del personal, rol, activar y desactivar, y reasignación de
 * la cartera de un vendedor. El usuario técnico `sistema` no se administra.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $usuarios = $this->administrables()
            ->with(['vendedor' => fn ($q) => $q->withCount('clientes'), 'cliente'])
            ->when($request->string('rol')->value(), fn ($q, $rol) => $q->whereHas('rol', fn ($q) => $q->where('nombre', $rol)))
            ->orderBy('email')
            ->paginate(25)
            ->withQueryString();

        return view('crm.admin.usuarios.index', ['usuarios' => $usuarios, 'roles' => Rol::orderBy('id_rol')->pluck('nombre')]);
    }

    public function create(): View
    {
        return view('crm.admin.usuarios.form', ['usuario' => new Usuario(['activo' => true]), 'roles' => Rol::PERSONAL]);
    }

    public function store(Request $request, AdministrarUsuarios $administrar): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email', 'max:120', Rule::unique('usuario', 'email')],
            'password' => ['required', 'confirmed', Password::min(10)],
            'rol' => ['required', Rule::in(Rol::PERSONAL)],
            'nombre' => ['required_if:rol,'.Rol::VENDEDOR, 'nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
        ]);

        $usuario = $administrar->crear($datos);

        return redirect()->route('crm.admin.usuarios.index')->with('estado', "Usuario {$usuario->email} creado.");
    }

    public function edit(int $usuario): View
    {
        $usuario = $this->administrables()->with('vendedor')->findOrFail($usuario);

        return view('crm.admin.usuarios.form', [
            'usuario' => $usuario,
            'roles' => Rol::PERSONAL,
            'destinos' => Vendedor::where('activo', true)
                ->whereKeyNot($usuario->vendedor?->id_vendedor)
                ->whereHas('usuario', fn ($q) => $q->where('activo', true))
                ->orderBy('nombre')->get(),
            'clientesEnCartera' => $usuario->vendedor?->clientes()->count() ?? 0,
        ]);
    }

    public function update(Request $request, int $usuario, AdministrarUsuarios $administrar): RedirectResponse
    {
        $usuario = $this->administrables()->findOrFail($usuario);

        $datos = $request->validate([
            'email' => ['required', 'email', 'max:120', Rule::unique('usuario', 'email')->ignore($usuario->id_usuario, 'id_usuario')],
            'password' => ['nullable', 'confirmed', Password::min(10)],
            'rol' => [$usuario->esPersonal() ? 'required' : 'prohibited', Rule::in(Rol::PERSONAL)],
            'nombre' => ['required_if:rol,'.Rol::VENDEDOR, 'nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
        ]);

        $administrar->actualizar($usuario, $datos + ['activo' => $request->boolean('activo')], $request->user());

        return redirect()->route('crm.admin.usuarios.index')->with('estado', "Usuario {$usuario->email} actualizado.");
    }

    public function reasignarCartera(Request $request, int $usuario, AdministrarUsuarios $administrar): RedirectResponse
    {
        $origen = $this->administrables()->with('vendedor')->findOrFail($usuario)->vendedor;
        abort_if($origen === null, 404);

        $datos = $request->validate([
            'id_vendedor_destino' => ['required', 'integer', Rule::exists('vendedor', 'id_vendedor')],
        ], [], ['id_vendedor_destino' => 'vendedor de destino']);

        $movidos = $administrar->reasignarCartera($origen, Vendedor::findOrFail($datos['id_vendedor_destino']));

        return redirect()->route('crm.admin.usuarios.edit', $usuario)->with('estado', "Se reasignaron {$movidos} clientes.");
    }

    private function administrables()
    {
        return Usuario::where('email', '<>', CatalogoSeeder::EMAIL_SISTEMA);
    }
}
