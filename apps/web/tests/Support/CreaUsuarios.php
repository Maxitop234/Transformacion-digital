<?php

namespace Tests\Support;

use App\Models\Cliente;
use App\Models\ListaPrecio;
use App\Models\Rol;
use App\Models\TipoCliente;
use App\Models\Usuario;
use App\Models\Vendedor;
use Database\Seeders\CatalogoSeeder;

/**
 * Usuarios y clientes mínimos sobre los catálogos reales. Datos ficticios.
 * Requiere `RefreshDatabase`.
 */
trait CreaUsuarios
{
    protected const CLAVE = 'clave-de-prueba';

    private int $secuenciaCorreo = 0;

    protected function sembrarCatalogos(): void
    {
        $this->seed(CatalogoSeeder::class);
    }

    protected function usuario(string $rol, bool $activo = true): Usuario
    {
        return Usuario::create([
            'id_rol' => Rol::where('nombre', $rol)->value('id_rol'),
            'email' => $rol.(++$this->secuenciaCorreo).'@prueba.example',
            'password_hash' => self::CLAVE,
            'activo' => $activo,
        ]);
    }

    protected function vendedor(string $nombre = 'Vendedor de prueba'): Usuario
    {
        $usuario = $this->usuario(Rol::VENDEDOR);
        Vendedor::create(['id_usuario' => $usuario->id_usuario, 'nombre' => $nombre]);

        return $usuario->refresh();
    }

    protected function clienteDe(?Usuario $vendedor, string $nombre = 'Empresa Demo de prueba', ?Usuario $cuenta = null): Cliente
    {
        return Cliente::create([
            'id_usuario' => $cuenta?->id_usuario,
            'id_tipo_cliente' => TipoCliente::where('codigo', 'B2B')->value('id_tipo_cliente'),
            'id_lista_precio' => ListaPrecio::where('nombre', CatalogoSeeder::LISTA_PUBLICA)->value('id_lista_precio'),
            'id_vendedor' => $vendedor?->vendedor->id_vendedor,
            'nombre_razon_social' => $nombre,
            'estado_cliente' => 'activo',
        ]);
    }
}
