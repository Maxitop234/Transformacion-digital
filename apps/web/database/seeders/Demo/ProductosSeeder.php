<?php

namespace Database\Seeders\Demo;

use App\Models\Categoria;
use App\Models\DetalleListaPrecio;
use App\Models\Inventario;
use App\Models\ListaPrecio;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\RangoConservacion;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;

/**
 * Catálogo de demostración: 32 productos genéricos (sin marcas), tres listas
 * de precios y el inventario inicial con su movimiento de ingreso.
 *
 * Los precios son netos en CLP (supuesto documentado en T-004).
 */
class ProductosSeeder extends Seeder
{
    /** Listas institucionales => factor sobre el precio público. */
    public const LISTAS_INSTITUCIONALES = [
        'Hospitales y clínicas 2026' => 0.86,
        'Casinos y colegios 2026' => 0.90,
    ];

    /** sku => [nombre, categoría, rango, unidad, precio público] */
    private const PRODUCTOS = [
        'CAR-001' => ['Lomo vetado de vacuno', 'Cárnicos', 'Cárnico fresco', 'kg', 12990],
        'CAR-002' => ['Posta negra de vacuno', 'Cárnicos', 'Cárnico fresco', 'kg', 9990],
        'CAR-003' => ['Carne molida de vacuno 10 % grasa', 'Cárnicos', 'Cárnico fresco', 'kg', 7490],
        'CAR-004' => ['Chuleta de cerdo centro', 'Cárnicos', 'Cárnico fresco', 'kg', 6490],
        'CAR-005' => ['Costillar de cerdo', 'Cárnicos', 'Cárnico fresco', 'kg', 5990],
        'CAR-006' => ['Hamburguesa de vacuno congelada, 10 unidades', 'Cárnicos', 'Congelado', 'caja', 8990],
        'AVE-001' => ['Pechuga de pollo deshuesada', 'Aves', 'Cárnico fresco', 'kg', 5990],
        'AVE-002' => ['Trutro entero de pollo', 'Aves', 'Cárnico fresco', 'kg', 3490],
        'AVE-003' => ['Pollo entero', 'Aves', 'Cárnico fresco', 'kg', 2990],
        'AVE-004' => ['Pechuga de pavo', 'Aves', 'Cárnico fresco', 'kg', 7990],
        'AVE-005' => ['Filetillo de pollo IQF 1 kg', 'Aves', 'Congelado', 'un', 5490],
        'AVE-006' => ['Nuggets de pollo 2 kg', 'Aves', 'Congelado', 'caja', 8490],
        'MAR-001' => ['Filete de salmón congelado', 'Pescados y mariscos', 'Congelado', 'kg', 13990],
        'MAR-002' => ['Filete de merluza congelado', 'Pescados y mariscos', 'Congelado', 'kg', 6990],
        'MAR-003' => ['Camarón cocido congelado 1 kg', 'Pescados y mariscos', 'Congelado', 'un', 9990],
        'MAR-004' => ['Choritos cocidos congelados 1 kg', 'Pescados y mariscos', 'Congelado', 'un', 3990],
        'MAR-005' => ['Filete de reineta congelado', 'Pescados y mariscos', 'Congelado', 'kg', 8990],
        'LAC-001' => ['Leche entera 1 L, 12 unidades', 'Lácteos', 'Refrigerado', 'caja', 11990],
        'LAC-002' => ['Yogur natural 1 kg', 'Lácteos', 'Refrigerado', 'un', 2490],
        'LAC-003' => ['Queso gauda laminado 1 kg', 'Lácteos', 'Refrigerado', 'un', 8990],
        'LAC-004' => ['Mantequilla con sal 250 g', 'Lácteos', 'Refrigerado', 'un', 2290],
        'LAC-005' => ['Crema de leche 1 L', 'Lácteos', 'Refrigerado', 'un', 3990],
        'LAC-006' => ['Quesillo fresco 500 g', 'Lácteos', 'Refrigerado', 'un', 2690],
        'CON-001' => ['Papas prefritas 2,5 kg', 'Congelados', 'Congelado', 'un', 4990],
        'CON-002' => ['Arvejas congeladas 1 kg', 'Congelados', 'Congelado', 'un', 2190],
        'CON-003' => ['Mezcla de verduras 1 kg', 'Congelados', 'Congelado', 'un', 2390],
        'CON-004' => ['Choclo congelado 1 kg', 'Congelados', 'Congelado', 'un', 2290],
        'CON-005' => ['Empanadas de pino congeladas, 12 unidades', 'Congelados', 'Congelado', 'caja', 9990],
        'HEL-001' => ['Helado de vainilla 5 L', 'Helados y postres', 'Congelado', 'un', 11990],
        'HEL-002' => ['Helado de chocolate 5 L', 'Helados y postres', 'Congelado', 'un', 11990],
        'HEL-003' => ['Torta helada 1,5 L', 'Helados y postres', 'Congelado', 'un', 6990],
        'HEL-004' => ['Paletas de agua, 20 unidades', 'Helados y postres', 'Congelado', 'caja', 8990],
    ];

    /** Stock inicial según la unidad de venta. */
    private const STOCK_INICIAL = ['kg' => 600, 'un' => 400, 'caja' => 200];

    public function run(): void
    {
        $publica = ListaPrecio::where('nombre', CatalogoSeeder::LISTA_PUBLICA)->firstOrFail();
        $listas = [];

        foreach (self::LISTAS_INSTITUCIONALES as $nombre => $factor) {
            $listas[] = [ListaPrecio::create(['nombre' => $nombre, 'vigente_desde' => CatalogoSeeder::INICIO_VIGENCIA]), $factor];
        }

        $categorias = Categoria::pluck('id_categoria', 'nombre');
        $rangos = RangoConservacion::pluck('id_rango', 'nombre');
        $sistema = DemoSeeder::idUsuarioSistema();
        $ingreso = DemoSeeder::inicio()->subDays(7);

        foreach (self::PRODUCTOS as $sku => [$nombre, $categoria, $rango, $unidad, $precio]) {
            $producto = Producto::create([
                'id_categoria' => $categorias[$categoria],
                'id_rango' => $rangos[$rango],
                'sku' => $sku,
                'nombre' => $nombre,
                'descripcion' => "{$nombre}. Producto de demostración.",
                'unidad_medida' => $unidad,
            ]);

            DetalleListaPrecio::create(['id_lista_precio' => $publica->id_lista_precio, 'id_producto' => $producto->id_producto, 'precio_unitario' => $precio]);

            foreach ($listas as [$lista, $factor]) {
                DetalleListaPrecio::create([
                    'id_lista_precio' => $lista->id_lista_precio,
                    'id_producto' => $producto->id_producto,
                    'precio_unitario' => round($precio * $factor, -1),
                ]);
            }

            $stock = self::STOCK_INICIAL[$unidad];
            Inventario::create(['id_producto' => $producto->id_producto, 'stock_disponible' => $stock, 'actualizado_en' => $ingreso]);
            MovimientoInventario::create([
                'id_producto' => $producto->id_producto,
                'id_usuario' => $sistema,
                'tipo' => 'ingreso',
                'cantidad' => $stock,
                'fecha_hora' => $ingreso,
            ]);
        }
    }
}
