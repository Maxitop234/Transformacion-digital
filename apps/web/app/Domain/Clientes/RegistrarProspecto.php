<?php

namespace App\Domain\Clientes;

use App\Models\Cliente;
use App\Models\ContactoCliente;
use App\Models\EtapaPipeline;
use App\Models\HistorialEtapa;
use App\Models\ListaPrecio;
use App\Models\Oportunidad;
use App\Models\TipoCliente;
use App\Models\Usuario;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Ingreso manual de un prospecto en el CRM (T-014). Nace sin cuenta de
 * portal, en estado `prospecto`, con la lista «Público general» y asignado
 * al vendedor que lo ingresa (o al que elige el administrador).
 *
 * Para que ningún prospecto quede sin seguimiento (BUSINESS_RULES.md §5 y
 * §6) se abre su oportunidad en «Prospecto nuevo» con su fila de
 * `historial_etapa`.
 */
final class RegistrarProspecto
{
    /**
     * @param  array{tipo: string, nombre_razon_social: string, rut?: string|null, origen: string, titulo_oportunidad: string, contacto_nombre?: string|null, contacto_cargo?: string|null, contacto_email?: string|null, contacto_telefono?: string|null}  $datos
     */
    public function __invoke(Usuario $actor, int $idVendedor, array $datos): Cliente
    {
        return DB::transaction(function () use ($actor, $idVendedor, $datos) {
            $cliente = Cliente::create([
                'id_tipo_cliente' => TipoCliente::where('codigo', $datos['tipo'])->valueOrFail('id_tipo_cliente'),
                'id_lista_precio' => ListaPrecio::where('nombre', CatalogoSeeder::LISTA_PUBLICA)->valueOrFail('id_lista_precio'),
                'id_vendedor' => $idVendedor,
                'rut' => filled($datos['rut'] ?? null) ? Rut::desdeTexto($datos['rut'])->normalizado() : null,
                'nombre_razon_social' => trim($datos['nombre_razon_social']),
                'estado_cliente' => 'prospecto',
                'fecha_registro' => now(),
            ]);

            if (filled($datos['contacto_nombre'] ?? null)) {
                ContactoCliente::create([
                    'id_cliente' => $cliente->id_cliente,
                    'nombre' => trim($datos['contacto_nombre']),
                    'cargo' => $datos['contacto_cargo'] ?? null,
                    'email' => trim($datos['contacto_email']),
                    'telefono' => $datos['contacto_telefono'] ?? null,
                    'es_principal' => true,
                ]);
            }

            $etapa = EtapaPipeline::where('nombre', 'Prospecto nuevo')->firstOrFail();
            $oportunidad = Oportunidad::create([
                'id_cliente' => $cliente->id_cliente,
                'id_vendedor' => $idVendedor,
                'id_etapa' => $etapa->id_etapa,
                'titulo' => trim($datos['titulo_oportunidad']),
                'origen' => $datos['origen'],
                'fecha_creacion' => now(),
            ]);
            HistorialEtapa::create([
                'id_oportunidad' => $oportunidad->id_oportunidad,
                'id_etapa' => $etapa->id_etapa,
                'id_usuario' => $actor->id_usuario,
                'fecha_hora' => now(),
                'comentario' => 'Prospecto ingresado en el CRM',
            ]);

            return $cliente;
        });
    }
}
