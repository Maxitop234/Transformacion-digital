<?php

namespace App\Domain\Catalogo;

use App\Models\Cliente;
use App\Models\DetalleListaPrecio;
use App\Models\ListaPrecio;
use App\Models\Producto;
use App\Models\Usuario;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Support\Collection;

/**
 * Servicio único de precios (RN-01, RN-02). Toda pantalla o proceso que
 * necesite el precio de un producto para alguien pasa por aquí: catálogo,
 * carrito (T-010) y pedido (T-011).
 *
 * La lista que aplica es la del cliente solo si su cuenta está activa (una
 * empresa pendiente de validación ve «Público general») y la lista está
 * activa y vigente hoy. En cualquier otro caso (visitante, personal, cuenta
 * pendiente, lista vencida) aplica «Público general».
 *
 * Los precios de lista son netos, en CLP.
 */
final class Precios
{
    public function perfil(?Usuario $usuario): PerfilPrecio
    {
        $publica = $this->listaPublica();
        $cliente = $usuario?->cliente()->with(['tipoCliente', 'listaPrecio'])->first();

        if ($cliente === null) {
            return new PerfilPrecio($publica, $usuario?->esPersonal() ? 'Personal de Cofrial' : 'Visitante', false, false, null);
        }

        return $this->perfilDeCliente($cliente);
    }

    /**
     * Perfil de un cliente sin pasar por su cuenta (pedido telefónico del CRM,
     * T-014): un prospecto sin usuario también tiene lista y estado.
     */
    public function perfilDeCliente(Cliente $cliente): PerfilPrecio
    {
        $cliente->loadMissing(['tipoCliente', 'listaPrecio']);
        $publica = $this->listaPublica();
        $esB2B = $cliente->tipoCliente->codigo === 'B2B';
        $activo = $cliente->estado_cliente === 'activo';
        $lista = $activo && $this->vigente($cliente->listaPrecio) ? $cliente->listaPrecio : $publica;

        $etiqueta = match (true) {
            $esB2B && ! $activo => 'Empresa pendiente de validación',
            $esB2B => 'Empresa (B2B)',
            default => 'Persona (B2C)',
        };

        return new PerfilPrecio($lista, $etiqueta, $esB2B, $activo, $cliente);
    }

    public function listaPara(?Usuario $usuario): ListaPrecio
    {
        return $this->perfil($usuario)->lista;
    }

    /** Precio neto del producto en la lista; null si la lista no lo incluye. */
    public function precio(Producto|int $producto, ListaPrecio $lista): ?string
    {
        $id = $producto instanceof Producto ? $producto->id_producto : $producto;

        return $this->precios([$id], $lista)->get($id);
    }

    /**
     * Precios netos de varios productos en una sola consulta.
     *
     * @param  iterable<int>  $idsProducto
     * @return Collection<int, string> id_producto => precio
     */
    public function precios(iterable $idsProducto, ListaPrecio $lista): Collection
    {
        return DetalleListaPrecio::where('id_lista_precio', $lista->id_lista_precio)
            ->whereIn('id_producto', collect($idsProducto)->all())
            ->pluck('precio_unitario', 'id_producto');
    }

    /** Precio con IVA, redondeado al peso (config `negocio.iva_pct`). */
    public function conIva(string|int|float $neto): int
    {
        return (int) round(((float) $neto) * (1 + config('negocio.iva_pct') / 100));
    }

    /** Formato de presentación: $12.345 (sin decimales). */
    public static function clp(string|int|float $monto): string
    {
        return '$'.number_format(round((float) $monto), 0, ',', '.');
    }

    public function listaPublica(): ListaPrecio
    {
        return ListaPrecio::where('nombre', CatalogoSeeder::LISTA_PUBLICA)->firstOrFail();
    }

    private function vigente(?ListaPrecio $lista): bool
    {
        if ($lista === null || ! $lista->activa) {
            return false;
        }

        // `app.timezone` es la zona de negocio (America/Santiago).
        $hoy = now(config('app.timezone'))->startOfDay();

        return $lista->vigente_desde->lte($hoy) && ($lista->vigente_hasta === null || $lista->vigente_hasta->gte($hoy));
    }
}
