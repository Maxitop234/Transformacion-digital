<?php

namespace App\Domain\Clientes;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Vendedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Usuarios del personal (administrador, vendedor, despachador). Las cuentas
 * de cliente nacen en el registro del portal (T-009) y aquí solo se activan
 * o desactivan.
 *
 * Un vendedor es un usuario con su fila en `vendedor`; `vendedor.activo`
 * sigue a `usuario.activo` mientras el rol sea vendedor.
 */
final class AdministrarUsuarios
{
    /** @param array{email: string, password: string, rol: string, nombre?: ?string, telefono?: ?string} $datos */
    public function crear(array $datos): Usuario
    {
        $this->exigirRolDePersonal($datos['rol']);

        return DB::transaction(function () use ($datos) {
            $usuario = Usuario::create([
                'id_rol' => Rol::where('nombre', $datos['rol'])->value('id_rol'),
                'email' => $datos['email'],
                'password_hash' => $datos['password'],
                'activo' => true,
            ]);

            if ($datos['rol'] === Rol::VENDEDOR) {
                Vendedor::create([
                    'id_usuario' => $usuario->id_usuario,
                    'nombre' => $datos['nombre'],
                    'telefono' => $datos['telefono'] ?? null,
                    'activo' => true,
                ]);
            }

            return $usuario;
        });
    }

    /**
     * @param  array{email: string, activo: bool, rol?: ?string, password?: ?string, nombre?: ?string, telefono?: ?string}  $datos
     */
    public function actualizar(Usuario $usuario, array $datos, Usuario $responsable): Usuario
    {
        $rolNuevo = $usuario->esPersonal() ? ($datos['rol'] ?? $usuario->nombreRol()) : $usuario->nombreRol();
        $activo = (bool) $datos['activo'];

        if ($usuario->esPersonal()) {
            $this->exigirRolDePersonal($rolNuevo);
        }

        if ($usuario->is($responsable) && (! $activo || $rolNuevo !== Rol::ADMINISTRADOR)) {
            throw ValidationException::withMessages(['activo' => 'No puedes desactivarte ni quitarte el rol de administrador.']);
        }

        $vendedor = $usuario->vendedor;
        $dejaDeSerVendedor = $usuario->nombreRol() === Rol::VENDEDOR && $rolNuevo !== Rol::VENDEDOR;

        if ($dejaDeSerVendedor && $vendedor && $vendedor->clientes()->exists()) {
            throw ValidationException::withMessages(['rol' => 'El vendedor tiene clientes asignados: reasigna su cartera antes de cambiarle el rol.']);
        }

        return DB::transaction(function () use ($usuario, $datos, $rolNuevo, $activo, $vendedor) {
            $usuario->fill([
                'email' => $datos['email'],
                'activo' => $activo,
                'id_rol' => Rol::where('nombre', $rolNuevo)->value('id_rol'),
            ]);

            if (! empty($datos['password'])) {
                $usuario->password_hash = $datos['password'];
            }

            $usuario->save();

            if ($rolNuevo === Rol::VENDEDOR) {
                Vendedor::updateOrCreate(['id_usuario' => $usuario->id_usuario], [
                    'nombre' => $datos['nombre'] ?? $vendedor?->nombre,
                    'telefono' => $datos['telefono'] ?? null,
                    'activo' => $activo,
                ]);
            } elseif ($vendedor) {
                $vendedor->update(['activo' => false]);
            }

            return $usuario->refresh();
        });
    }

    /**
     * Mueve toda la cartera de un vendedor a otro (RN-10). Devuelve cuántos
     * clientes se movieron.
     */
    public function reasignarCartera(Vendedor $origen, Vendedor $destino): int
    {
        if ($origen->is($destino)) {
            throw ValidationException::withMessages(['id_vendedor_destino' => 'Elige un vendedor distinto.']);
        }

        if (! $destino->activo || ! $destino->usuario->activo) {
            throw ValidationException::withMessages(['id_vendedor_destino' => 'El vendedor de destino está inactivo.']);
        }

        return DB::transaction(fn () => Cliente::where('id_vendedor', $origen->id_vendedor)
            ->update(['id_vendedor' => $destino->id_vendedor]));
    }

    private function exigirRolDePersonal(string $rol): void
    {
        if (! in_array($rol, Rol::PERSONAL, true)) {
            throw ValidationException::withMessages(['rol' => 'Solo se crean aquí usuarios del personal.']);
        }
    }
}
