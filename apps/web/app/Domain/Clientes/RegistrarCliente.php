<?php

namespace App\Domain\Clientes;

use App\Models\Cliente;
use App\Models\ContactoCliente;
use App\Models\DireccionDespacho;
use App\Models\EtapaPipeline;
use App\Models\HistorialEtapa;
use App\Models\ListaPrecio;
use App\Models\Oportunidad;
use App\Models\Rol;
use App\Models\TipoCliente;
use App\Models\Usuario;
use App\Models\Vendedor;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registro de clientes desde el portal (T-009, BUSINESS_RULES.md §2 y §6).
 *
 * - Persona (B2C): `usuario` + `cliente` activo con «Público general» +
 *   `direccion_despacho`. Puede comprar de inmediato.
 * - Empresa (B2B): además, `contacto_cliente` principal y una oportunidad
 *   «Prospecto nuevo» con origen `portal`. Queda `pendiente_validacion`
 *   (RN-02): ve el catálogo, pero no compra hasta que un ejecutivo la valide.
 *   Se asigna al vendedor activo con menos clientes en su cartera, para que la
 *   vea en su panel (RN-10) y ningún prospecto digital quede sin seguimiento.
 *
 * Todo ocurre en una sola transacción. El RUT llega ya validado (formato,
 * dígito verificador y unicidad) desde el controlador y se guarda normalizado.
 */
final class RegistrarCliente
{
    /**
     * @param  array{nombre: string, email: string, password: string, direccion: array<string, mixed>}  $datos
     */
    public function persona(array $datos): Usuario
    {
        return DB::transaction(function () use ($datos) {
            $usuario = $this->usuario(Rol::CLIENTE_B2C, $datos['email'], $datos['password']);
            $cliente = $this->cliente($usuario, 'B2C', trim($datos['nombre']), null, 'activo', null);
            $this->direccion($cliente, $datos['direccion'], 'Mi dirección');

            return $usuario;
        });
    }

    /**
     * @param  array{rut: string, razon_social: string, email: string, password: string, contacto_nombre: string, contacto_cargo?: string|null, contacto_telefono?: string|null, direccion: array<string, mixed>}  $datos
     */
    public function empresa(array $datos): Usuario
    {
        return DB::transaction(function () use ($datos) {
            $vendedor = $this->vendedorParaAsignar();
            $usuario = $this->usuario(Rol::CLIENTE_B2B, $datos['email'], $datos['password']);
            $cliente = $this->cliente(
                $usuario,
                'B2B',
                trim($datos['razon_social']),
                Rut::desdeTexto($datos['rut'])->normalizado(),
                'pendiente_validacion',
                $vendedor?->id_vendedor,
            );

            ContactoCliente::create([
                'id_cliente' => $cliente->id_cliente,
                'nombre' => trim($datos['contacto_nombre']),
                'cargo' => filled($datos['contacto_cargo'] ?? null) ? trim($datos['contacto_cargo']) : null,
                'email' => $usuario->email,
                'telefono' => filled($datos['contacto_telefono'] ?? null) ? trim($datos['contacto_telefono']) : null,
                'es_principal' => true,
            ]);

            $this->direccion($cliente, $datos['direccion'], 'Bodega principal');

            if ($vendedor !== null) {
                $this->abrirOportunidad($cliente, $vendedor, $usuario);
            }

            return $usuario;
        });
    }

    private function usuario(string $rol, string $email, string $password): Usuario
    {
        return Usuario::create([
            'id_rol' => Rol::where('nombre', $rol)->valueOrFail('id_rol'),
            'email' => Str::lower(trim($email)),
            'password_hash' => $password,
            'activo' => true,
            'creado_en' => now(),
        ]);
    }

    private function cliente(Usuario $usuario, string $tipo, string $nombre, ?string $rut, string $estado, ?int $idVendedor): Cliente
    {
        return Cliente::create([
            'id_usuario' => $usuario->id_usuario,
            'id_tipo_cliente' => TipoCliente::where('codigo', $tipo)->valueOrFail('id_tipo_cliente'),
            'id_lista_precio' => ListaPrecio::where('nombre', CatalogoSeeder::LISTA_PUBLICA)->valueOrFail('id_lista_precio'),
            'id_vendedor' => $idVendedor,
            'rut' => $rut,
            'nombre_razon_social' => $nombre,
            'estado_cliente' => $estado,
            'fecha_registro' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $datos */
    private function direccion(Cliente $cliente, array $datos, string $aliasPorDefecto): DireccionDespacho
    {
        return DireccionDespacho::create([
            'id_cliente' => $cliente->id_cliente,
            'id_comuna' => (int) $datos['id_comuna'],
            'alias' => filled($datos['alias'] ?? null) ? trim($datos['alias']) : $aliasPorDefecto,
            'calle' => trim($datos['calle']),
            'referencia' => filled($datos['referencia'] ?? null) ? trim($datos['referencia']) : null,
            'ventana_inicio' => $datos['ventana_inicio'] ?? null,
            'ventana_fin' => $datos['ventana_fin'] ?? null,
            'activa' => true,
        ]);
    }

    private function abrirOportunidad(Cliente $cliente, Vendedor $vendedor, Usuario $usuario): void
    {
        $etapa = EtapaPipeline::where('nombre', 'Prospecto nuevo')->firstOrFail();

        $oportunidad = Oportunidad::create([
            'id_cliente' => $cliente->id_cliente,
            'id_vendedor' => $vendedor->id_vendedor,
            'id_etapa' => $etapa->id_etapa,
            'titulo' => "Registro web de {$cliente->nombre_razon_social}",
            'origen' => 'portal',
            'fecha_creacion' => now(),
        ]);

        HistorialEtapa::create([
            'id_oportunidad' => $oportunidad->id_oportunidad,
            'id_etapa' => $etapa->id_etapa,
            'id_usuario' => $usuario->id_usuario,
            'fecha_hora' => now(),
            'comentario' => 'Empresa registrada en el portal',
        ]);
    }

    /**
     * Vendedor activo con menos clientes en su cartera (empate: el más
     * antiguo). Null si no hay vendedores: la empresa queda sin asignar y el
     * administrador la ve en su panel.
     */
    private function vendedorParaAsignar(): ?Vendedor
    {
        return Vendedor::where('activo', true)
            ->whereHas('usuario', fn ($q) => $q->where('activo', true))
            ->withCount('clientes')
            ->orderBy('clientes_count')
            ->orderBy('id_vendedor')
            ->first();
    }
}
