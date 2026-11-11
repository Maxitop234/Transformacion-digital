<?php

namespace Tests\Feature\Portal;

use App\Domain\Catalogo\Precios;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DetalleListaPrecio;
use App\Models\Inventario;
use App\Models\ListaPrecio;
use App\Models\Producto;
use App\Models\RangoConservacion;
use App\Models\Rol;
use App\Models\TipoCliente;
use App\Models\Usuario;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreaUsuarios;
use Tests\TestCase;

/** Catálogo con precios por lista (T-008). Datos ficticios. */
class CatalogoTest extends TestCase
{
    use CreaUsuarios;
    use RefreshDatabase;

    private ListaPrecio $publica;

    private ListaPrecio $institucional;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();

        $this->publica = ListaPrecio::where('nombre', CatalogoSeeder::LISTA_PUBLICA)->firstOrFail();
        $this->institucional = ListaPrecio::create(['nombre' => 'Convenio de prueba', 'vigente_desde' => '2026-01-01', 'activa' => true]);
    }

    // --- Precio según el perfil (RN-01, RN-02) -----------------------------

    public function test_rn01_el_mismo_producto_tiene_precios_distintos_para_b2c_y_para_b2b_con_lista_institucional(): void
    {
        $producto = $this->producto('TST-001', publico: 10000, institucional: 8000);
        $b2c = $this->cuentaCliente('B2C', $this->publica);
        $b2b = $this->cuentaCliente('B2B', $this->institucional);

        // B2C: lista pública con IVA (10.000 × 1,19).
        $this->actingAs($b2c)->get('/catalogo')
            ->assertOk()->assertSee('Público general')->assertSee('$11.900')->assertDontSee('$8.000');
        $this->actingAs($b2c)->get('/productos/TST-001')->assertSee('$11.900');

        // B2B validado: lista institucional en neto.
        $this->actingAs($b2b)->get('/catalogo')
            ->assertOk()->assertSee('Convenio de prueba')->assertSee('$8.000')->assertDontSee('$11.900');
        $this->actingAs($b2b)->get('/productos/TST-001')->assertSee('$8.000');

        $precios = app(Precios::class);
        $this->assertSame('10000.00', $precios->precio($producto, $precios->listaPara($b2c)));
        $this->assertSame('8000.00', $precios->precio($producto, $precios->listaPara($b2b)));
    }

    public function test_rn02_una_empresa_pendiente_de_validacion_ve_precios_de_publico_general(): void
    {
        $this->producto('TST-001', publico: 10000, institucional: 8000);
        $pendiente = $this->cuentaCliente('B2B', $this->institucional, 'pendiente_validacion');

        $this->actingAs($pendiente)->get('/catalogo')
            ->assertOk()
            ->assertSee('Empresa pendiente de validación')
            ->assertSee('Público general')
            ->assertSee('$10.000')
            ->assertDontSee('$8.000');

        $this->assertTrue(app(Precios::class)->listaPara($pendiente)->is($this->publica));

        // RN-02: ve el catálogo, pero aún no compra.
        $this->actingAs($pendiente)->get('/productos/TST-001')
            ->assertSee('data-agregar="TST-001" disabled', false);
    }

    public function test_un_visitante_y_el_personal_ven_publico_general(): void
    {
        $this->producto('TST-001', publico: 10000, institucional: 8000);

        $this->get('/catalogo')->assertOk()->assertSee('Visitante')->assertSee('Público general')->assertSee('$11.900');
        $this->actingAs($this->vendedor())->get('/catalogo')->assertOk()->assertSee('Público general')->assertSee('$11.900');
    }

    public function test_una_lista_vencida_o_inactiva_vuelve_a_publico_general(): void
    {
        $this->producto('TST-001', publico: 10000, institucional: 8000);
        $b2b = $this->cuentaCliente('B2B', $this->institucional);
        $precios = app(Precios::class);

        $this->institucional->update(['vigente_hasta' => now()->subDay()->toDateString()]);
        $this->assertTrue($precios->listaPara($b2b->refresh())->is($this->publica));

        $this->institucional->update(['vigente_hasta' => null, 'activa' => false]);
        $this->assertTrue($precios->listaPara($b2b->refresh())->is($this->publica));

        $this->institucional->update(['activa' => true]);
        $this->assertTrue($precios->listaPara($b2b->refresh())->is($this->institucional));
    }

    public function test_un_producto_sin_precio_en_la_lista_no_se_puede_agregar(): void
    {
        $this->producto('TST-001', publico: 10000);
        $b2b = $this->cuentaCliente('B2B', $this->institucional);

        $this->actingAs($b2b)->get('/productos/TST-001')
            ->assertSee('Precio no disponible en tu lista')
            ->assertSee('data-agregar="TST-001" disabled', false);
    }

    // --- Disponibilidad (RN-04) -------------------------------------------

    public function test_rn04_un_producto_sin_disponible_aparece_sin_stock_y_no_se_puede_agregar(): void
    {
        $this->producto('TST-CON', publico: 1000, stock: 10);
        // Todo el stock está reservado: disponible para venta = 0.
        $this->producto('TST-RES', publico: 1000, stock: 5, reservado: 5);
        $b2c = $this->cuentaCliente('B2C', $this->publica);

        $this->actingAs($b2c)->get('/productos/TST-RES')
            ->assertSee('Sin stock')
            ->assertSee('data-agregar="TST-RES" disabled', false);

        $this->actingAs($b2c)->get('/productos/TST-CON')
            ->assertDontSee('Sin stock')
            ->assertSee('data-agregar="TST-CON"', false)
            ->assertDontSee('data-agregar="TST-CON" disabled', false);

        $this->assertSame(0.0, Producto::where('sku', 'TST-RES')->first()->disponibleParaVenta());
    }

    // --- Listado y filtros ------------------------------------------------

    public function test_los_productos_inactivos_no_se_listan_ni_tienen_detalle(): void
    {
        $this->producto('TST-ACT', publico: 1000, nombre: 'Producto activo');
        $this->producto('TST-INA', publico: 1000, nombre: 'Producto retirado', activo: false);

        $this->get('/catalogo')->assertSee('Producto activo')->assertDontSee('Producto retirado');
        $this->get('/catalogo?q=retirado')->assertDontSee('Producto retirado');
        $this->get('/productos/TST-INA')->assertNotFound();
        $this->get('/productos/NO-EXISTE')->assertNotFound();
    }

    public function test_los_filtros_se_pueden_combinar(): void
    {
        [$lacteos, $carnicos] = [$this->categoria('Lácteos'), $this->categoria('Cárnicos')];
        [$refrigerado, $congelado] = [$this->rango('Refrigerado'), $this->rango('Congelado')];

        $this->producto('TST-1', publico: 1000, nombre: 'Leche entera', categoria: $lacteos, rango: $refrigerado, stock: 10);
        $this->producto('TST-2', publico: 1000, nombre: 'Leche sin stock', categoria: $lacteos, rango: $refrigerado, stock: 0);
        $this->producto('TST-3', publico: 1000, nombre: 'Leche helada', categoria: $lacteos, rango: $congelado, stock: 10);
        $this->producto('TST-4', publico: 1000, nombre: 'Lomo vetado', categoria: $carnicos, rango: $refrigerado, stock: 10);

        $consulta = http_build_query(['categoria' => $lacteos, 'rango' => $refrigerado, 'disponible' => 1, 'q' => 'leche']);

        $this->get("/catalogo?{$consulta}")->assertOk()
            ->assertSee('Leche entera')
            ->assertDontSee('Leche sin stock')
            ->assertDontSee('Leche helada')
            ->assertDontSee('Lomo vetado')
            ->assertSee('1 producto');

        $this->get('/catalogo?categoria='.$lacteos.'&q=LECHE')
            ->assertSee('Leche entera')->assertSee('Leche sin stock')->assertSee('Leche helada')->assertDontSee('Lomo vetado');

        $this->get('/catalogo?rango='.$refrigerado.'&disponible=1')
            ->assertSee('Leche entera')->assertSee('Lomo vetado')->assertDontSee('Leche sin stock')->assertDontSee('Leche helada');

        $this->get('/catalogo?q=TST-4')->assertSee('Lomo vetado')->assertDontSee('Leche entera');
    }

    public function test_la_busqueda_trata_los_comodines_como_texto(): void
    {
        $this->producto('TST-1', publico: 1000, nombre: 'Queso mantecoso');

        $this->get('/catalogo?q=%25')->assertOk()->assertDontSee('Queso mantecoso');
    }

    public function test_un_filtro_invalido_no_rompe_la_pagina(): void
    {
        $this->get('/catalogo?categoria=abc')->assertRedirect();
    }

    // --- Apoyo ------------------------------------------------------------

    private function producto(
        string $sku,
        ?int $publico = null,
        ?int $institucional = null,
        string $nombre = 'Producto de prueba',
        ?int $categoria = null,
        ?int $rango = null,
        int $stock = 10,
        int $reservado = 0,
        bool $activo = true,
    ): Producto {
        $producto = Producto::create([
            'id_categoria' => $categoria ?? Categoria::value('id_categoria'),
            'id_rango' => $rango ?? RangoConservacion::value('id_rango'),
            'sku' => $sku,
            'nombre' => $nombre,
            'unidad_medida' => 'un',
            'activo' => $activo,
        ]);
        Inventario::create(['id_producto' => $producto->id_producto, 'stock_disponible' => $stock, 'stock_reservado' => $reservado]);

        foreach ([[$this->publica, $publico], [$this->institucional, $institucional]] as [$lista, $precio]) {
            if ($precio !== null) {
                DetalleListaPrecio::create(['id_lista_precio' => $lista->id_lista_precio, 'id_producto' => $producto->id_producto, 'precio_unitario' => $precio]);
            }
        }

        return $producto;
    }

    private function cuentaCliente(string $tipo, ListaPrecio $lista, string $estado = 'activo'): Usuario
    {
        $usuario = $this->usuario($tipo === 'B2B' ? Rol::CLIENTE_B2B : Rol::CLIENTE_B2C);

        Cliente::create([
            'id_usuario' => $usuario->id_usuario,
            'id_tipo_cliente' => TipoCliente::where('codigo', $tipo)->value('id_tipo_cliente'),
            'id_lista_precio' => $lista->id_lista_precio,
            'nombre_razon_social' => "Cliente {$tipo} de prueba",
            'estado_cliente' => $estado,
        ]);

        return $usuario;
    }

    private function categoria(string $nombre): int
    {
        return Categoria::firstOrCreate(['nombre' => $nombre])->id_categoria;
    }

    private function rango(string $nombre): int
    {
        return RangoConservacion::where('nombre', $nombre)->value('id_rango');
    }
}
