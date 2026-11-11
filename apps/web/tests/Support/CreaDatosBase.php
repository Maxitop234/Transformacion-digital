<?php

namespace Tests\Support;

use App\Models\Camion;
use App\Models\Carrito;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Comuna;
use App\Models\DireccionDespacho;
use App\Models\EstadoPedido;
use App\Models\Inventario;
use App\Models\ListaPrecio;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\RangoConservacion;
use App\Models\Region;
use App\Models\Rol;
use App\Models\TipoCliente;
use App\Models\Usuario;

/**
 * Filas mínimas para probar restricciones. Datos ficticios (AGENTS.md).
 */
trait CreaDatosBase
{
    protected function crearCliente(): Cliente
    {
        $tipo = TipoCliente::create(['codigo' => 'B2C', 'nombre' => 'Público general']);
        $lista = ListaPrecio::create(['nombre' => 'Público general']);

        return Cliente::create([
            'id_tipo_cliente' => $tipo->id_tipo_cliente,
            'id_lista_precio' => $lista->id_lista_precio,
            'nombre_razon_social' => 'Cliente de prueba',
        ]);
    }

    protected function crearDireccion(Cliente $cliente): DireccionDespacho
    {
        $region = Region::create(['nombre' => 'La Araucanía']);
        $comuna = Comuna::create(['id_region' => $region->id_region, 'nombre' => 'Temuco']);

        return DireccionDespacho::create([
            'id_cliente' => $cliente->id_cliente,
            'id_comuna' => $comuna->id_comuna,
            'alias' => 'Casa',
            'calle' => 'Calle Ficticia 123',
        ]);
    }

    protected function crearProducto(): Producto
    {
        $categoria = Categoria::create(['nombre' => 'Lácteos']);
        $rango = RangoConservacion::create(['nombre' => 'Refrigerado', 'temp_min_c' => 0, 'temp_max_c' => 5]);

        return Producto::create([
            'id_categoria' => $categoria->id_categoria,
            'id_rango' => $rango->id_rango,
            'sku' => 'LAC-001',
            'nombre' => 'Leche entera 1 L',
            'unidad_medida' => 'un',
        ]);
    }

    protected function crearInventario(Producto $producto): Inventario
    {
        return Inventario::create(['id_producto' => $producto->id_producto, 'stock_disponible' => 10]);
    }

    protected function crearCarrito(Cliente $cliente): Carrito
    {
        return Carrito::create(['id_cliente' => $cliente->id_cliente]);
    }

    protected function crearPedido(?Cliente $cliente = null): Pedido
    {
        $cliente ??= $this->crearCliente();
        $estado = EstadoPedido::create(['nombre' => 'Pendiente', 'orden' => 1]);

        return Pedido::create([
            'id_direccion' => $this->crearDireccion($cliente)->id_direccion,
            'id_estado_pedido' => $estado->id_estado_pedido,
            'canal' => 'portal',
        ]);
    }

    protected function crearUsuario(): Usuario
    {
        $rol = Rol::create(['nombre' => 'administrador']);

        return Usuario::create([
            'id_rol' => $rol->id_rol,
            'email' => 'admin@cofrial.example',
            'password_hash' => 'secreto-de-prueba',
        ]);
    }

    protected function crearCamion(): Camion
    {
        return Camion::create(['camion_id' => 'CAM-001', 'patente' => 'ABCD12', 'capacidad_kg' => 3000]);
    }
}
