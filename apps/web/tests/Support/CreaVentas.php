<?php

namespace Tests\Support;

use App\Domain\Ventas\CalendarioEntregas;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Comuna;
use App\Models\DetalleListaPrecio;
use App\Models\DireccionDespacho;
use App\Models\Inventario;
use App\Models\ListaPrecio;
use App\Models\Producto;
use App\Models\RangoConservacion;
use App\Models\Rol;
use App\Models\TipoCliente;
use App\Models\Usuario;
use Database\Seeders\CatalogoSeeder;

/**
 * Productos con precio y stock, y cuentas de cliente con punto de entrega,
 * para los tests de carrito y pedidos. Datos ficticios. Usa `CreaUsuarios`.
 */
trait CreaVentas
{
    protected ListaPrecio $publica;

    protected ListaPrecio $convenio;

    protected function prepararVentas(): void
    {
        $this->sembrarCatalogos();
        $this->publica = ListaPrecio::where('nombre', CatalogoSeeder::LISTA_PUBLICA)->firstOrFail();
        $this->convenio = ListaPrecio::create(['nombre' => 'Convenio de prueba', 'vigente_desde' => '2026-01-01', 'activa' => true]);
    }

    protected function productoVenta(string $sku, ?int $publico = null, ?int $convenio = null, int $stock = 100, int $reservado = 0, string $nombre = 'Producto de prueba'): Producto
    {
        $producto = Producto::create([
            'id_categoria' => Categoria::value('id_categoria'),
            'id_rango' => RangoConservacion::value('id_rango'),
            'sku' => $sku,
            'nombre' => $nombre,
            'unidad_medida' => 'un',
            'activo' => true,
        ]);
        Inventario::create(['id_producto' => $producto->id_producto, 'stock_disponible' => $stock, 'stock_reservado' => $reservado]);

        foreach ([[$this->publica, $publico], [$this->convenio, $convenio]] as [$lista, $precio]) {
            if ($precio !== null) {
                DetalleListaPrecio::create(['id_lista_precio' => $lista->id_lista_precio, 'id_producto' => $producto->id_producto, 'precio_unitario' => $precio]);
            }
        }

        return $producto;
    }

    /** Cuenta de cliente activa con un punto de entrega activo. */
    protected function compradorCon(string $tipo, ?Usuario $vendedor = null, string $estado = 'activo'): Usuario
    {
        $usuario = $this->usuario($tipo === 'B2B' ? Rol::CLIENTE_B2B : Rol::CLIENTE_B2C);

        $cliente = Cliente::create([
            'id_usuario' => $usuario->id_usuario,
            'id_tipo_cliente' => TipoCliente::where('codigo', $tipo)->value('id_tipo_cliente'),
            'id_lista_precio' => ($tipo === 'B2B' ? $this->convenio : $this->publica)->id_lista_precio,
            'id_vendedor' => $vendedor?->vendedor->id_vendedor,
            'nombre_razon_social' => "Cliente {$tipo} de prueba",
            'estado_cliente' => $estado,
        ]);

        $this->direccionDe($cliente);

        return $usuario->refresh();
    }

    protected function direccionDe(Cliente $cliente, bool $activa = true): DireccionDespacho
    {
        return DireccionDespacho::create([
            'id_cliente' => $cliente->id_cliente,
            'id_comuna' => Comuna::where('nombre', 'Temuco')->value('id_comuna'),
            'alias' => 'Bodega de prueba',
            'calle' => 'Calle Ficticia 123',
            'ventana_inicio' => '08:00',
            'ventana_fin' => '12:00',
            'activa' => $activa,
        ]);
    }

    /** Datos válidos de `POST /pedidos` para la cuenta. */
    protected function datosCheckout(Usuario $cuenta, array $extra = []): array
    {
        return [
            'id_direccion' => DireccionDespacho::where('id_cliente', $cuenta->cliente->id_cliente)->value('id_direccion'),
            'fecha_entrega_solicitada' => app(CalendarioEntregas::class)->primeraFecha()->toDateString(),
            ...($cuenta->tieneRol(Rol::CLIENTE_B2B) ? ['orden_compra' => 'OC-PRUEBA-1'] : []),
            ...$extra,
        ];
    }
}
