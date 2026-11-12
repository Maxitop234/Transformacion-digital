<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Clientes\RegistrarCliente;
use App\Domain\Clientes\Rut;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Comuna;
use App\Models\Usuario;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Registro de cuentas de cliente en el portal (T-009, API.md §1.2). La
 * lógica vive en `App\Domain\Clientes\RegistrarCliente`; aquí se valida la
 * entrada (incluido el consentimiento de datos personales, SECURITY.md §1)
 * y se inicia la sesión de la cuenta nueva.
 */
class RegistroController extends Controller
{
    private const MENSAJES = [
        'consentimiento.accepted' => 'Debes aceptar el tratamiento de tus datos personales para crear la cuenta.',
    ];

    public function persona(): View
    {
        return view('auth.registro', ['tipo' => 'persona', 'comunas' => $this->comunas()]);
    }

    public function empresa(): View
    {
        return view('auth.registro', ['tipo' => 'empresa', 'comunas' => $this->comunas()]);
    }

    public function registrarPersona(Request $request, RegistrarCliente $registrar): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            ...$this->reglasCuenta(),
            ...$this->reglasDireccion(ventanaObligatoria: false),
        ], self::MENSAJES, $this->atributos());

        $usuario = $this->registrar(fn () => $registrar->persona([
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'password' => $datos['password'],
            'direccion' => $datos,
        ]));

        return $this->entrar($request, $usuario, 'Tu cuenta quedó activa. Ya puedes comprar.');
    }

    public function registrarEmpresa(Request $request, RegistrarCliente $registrar): RedirectResponse
    {
        $datos = $request->validate([
            'rut' => ['required', 'string', 'max:12', $this->reglaRut()],
            'razon_social' => ['required', 'string', 'max:150'],
            'contacto_nombre' => ['required', 'string', 'max:100'],
            'contacto_cargo' => ['nullable', 'string', 'max:80'],
            'contacto_telefono' => ['nullable', 'string', 'max:20'],
            ...$this->reglasCuenta(),
            ...$this->reglasDireccion(ventanaObligatoria: true),
        ], self::MENSAJES, $this->atributos());

        $usuario = $this->registrar(fn () => $registrar->empresa([...$datos, 'direccion' => $datos]));

        return $this->entrar($request, $usuario, 'Recibimos la solicitud de tu empresa. Ya puedes ver el catálogo con precios de lista; podrás comprar cuando un ejecutivo la valide.');
    }

    /** @return array<string, list<mixed>> */
    private function reglasCuenta(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:120', function (string $campo, mixed $valor, Closure $falla) {
                if (Usuario::where('email', Str::lower(trim((string) $valor)))->exists()) {
                    $falla('Ya existe una cuenta con ese correo.');
                }
            }],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'consentimiento' => ['accepted'],
        ];
    }

    /** @return array<string, list<mixed>> */
    private function reglasDireccion(bool $ventanaObligatoria): array
    {
        $ventana = $ventanaObligatoria ? 'required' : 'nullable';

        return [
            'alias' => ['nullable', 'string', 'max:60'],
            'calle' => ['required', 'string', 'max:150'],
            'id_comuna' => ['required', 'integer', Rule::exists('comuna', 'id_comuna')],
            'referencia' => ['nullable', 'string', 'max:200'],
            'ventana_inicio' => [$ventana, 'nullable', 'date_format:H:i', 'required_with:ventana_fin'],
            'ventana_fin' => [$ventana, 'nullable', 'date_format:H:i', 'required_with:ventana_inicio', 'after:ventana_inicio'],
        ];
    }

    /** RUT con dígito verificador válido y no registrado (se compara normalizado). */
    private function reglaRut(): Closure
    {
        return function (string $campo, mixed $valor, Closure $falla) {
            if (! Rut::esValido((string) $valor)) {
                $falla('El RUT no es válido: revisa el número y el dígito verificador.');

                return;
            }

            if (Cliente::where('rut', Rut::desdeTexto((string) $valor)->normalizado())->exists()) {
                $falla('Ese RUT ya está registrado. Si tu empresa ya tiene cuenta, contacta a tu ejecutivo.');
            }
        };
    }

    /** Dos registros simultáneos con el mismo correo o RUT: el segundo choca con el índice único. */
    private function registrar(Closure $accion): Usuario
    {
        try {
            return $accion();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Ya existe una cuenta con ese correo o RUT.']);
        }
    }

    private function entrar(Request $request, Usuario $usuario, string $mensaje): RedirectResponse
    {
        Auth::login($usuario);
        $request->session()->regenerate();
        $usuario->forceFill(['ultimo_acceso' => now()])->saveQuietly();

        return redirect()->route('portal.catalogo')->with('estado', $mensaje);
    }

    /** @return Collection<int, Comuna> */
    private function comunas()
    {
        return Comuna::orderBy('nombre')->get(['id_comuna', 'nombre']);
    }

    /** @return array<string, string> */
    private function atributos(): array
    {
        return [
            'razon_social' => 'razón social',
            'contacto_nombre' => 'nombre del contacto',
            'contacto_cargo' => 'cargo',
            'contacto_telefono' => 'teléfono',
            'id_comuna' => 'comuna',
            'ventana_inicio' => 'inicio de la ventana',
            'ventana_fin' => 'fin de la ventana',
            'consentimiento' => 'consentimiento',
        ];
    }
}
