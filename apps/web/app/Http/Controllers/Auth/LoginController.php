<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Inicio y cierre de sesión de clientes y personal sobre `usuario`.
 */
class LoginController extends Controller
{
    /** Intentos fallidos permitidos por cuenta e IP antes de bloquear. */
    private const MAX_INTENTOS = 5;

    /** Segundos de bloqueo tras agotar los intentos. */
    private const BLOQUEO_SEGUNDOS = 60;

    public function mostrar(): View
    {
        return view('auth.login');
    }

    public function iniciar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email', 'max:120'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $email = Str::lower(trim($datos['email']));
        $clave = $this->claveLimite($email, $request);

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($clave)]),
            ]);
        }

        // Un usuario inactivo no entra; el mensaje es el mismo que con una
        // contraseña incorrecta para no revelar qué cuentas existen.
        if (! Auth::attempt(['email' => $email, 'password' => $datos['password'], 'activo' => true])) {
            RateLimiter::hit($clave, self::BLOQUEO_SEGUNDOS);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($clave);
        $request->session()->regenerate();

        /** @var Usuario $usuario */
        $usuario = Auth::user();
        $usuario->forceFill(['ultimo_acceso' => now()])->saveQuietly();

        // Un cliente que antes intentó abrir el CRM no debe volver a un 403.
        $destino = $request->session()->get('url.intended');
        if ($destino && ! $usuario->esPersonal() && str_starts_with((string) parse_url($destino, PHP_URL_PATH), '/crm')) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended($usuario->rutaInicio());
    }

    public function cerrar(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.inicio');
    }

    private function claveLimite(string $email, Request $request): string
    {
        return 'login|'.Str::transliterate($email).'|'.$request->ip();
    }
}
