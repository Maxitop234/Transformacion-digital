<?php

namespace Tests\Feature\Esquema;

use App\Models\DetallePedido;
use App\Models\HistorialEstadoPedido;
use App\Models\Inventario;
use App\Models\Modelo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use Tests\Support\CreaDatosBase;
use Tests\TestCase;

class ModelosTest extends TestCase
{
    use CreaDatosBase;
    use RefreshDatabase;

    public function test_hay_un_modelo_por_tabla_con_columnas_y_relaciones_validas(): void
    {
        $modelos = $this->modelos();
        $tablas = array_map(fn (Model $m) => $m->getTable(), $modelos);
        sort($tablas);
        $esperadas = TablasTest::TABLAS_DE_NEGOCIO;
        sort($esperadas);

        $this->assertSame($esperadas, $tablas);

        foreach ($modelos as $modelo) {
            $tabla = $modelo->getTable();
            $columnas = Schema::getColumnListing($tabla);

            foreach ((array) $modelo->getKeyName() as $clave) {
                $this->assertContains($clave, $columnas, "{$tabla}: clave {$clave}");
            }

            foreach ($modelo->getFillable() as $columna) {
                $this->assertContains($columna, $columnas, "{$tabla}: fillable {$columna}");
            }

            foreach ($this->relaciones($modelo) as $nombre => $relacion) {
                // Resolver la consulta de cada relación valida tabla y columnas.
                $relacion->getQuery()->limit(1)->get();
                $this->assertTrue(true, "{$tabla}->{$nombre}()");
            }
        }
    }

    public function test_clave_compuesta_actualiza_solo_su_fila(): void
    {
        $pedido = $this->crearPedido();
        $producto = $this->crearProducto();
        $otro = $producto->replicate()->fill(['sku' => 'LAC-002']);
        $otro->save();

        $linea = DetallePedido::create(['id_pedido' => $pedido->id_pedido, 'id_producto' => $producto->id_producto, 'cantidad' => 2, 'precio_unitario_pactado' => 990]);
        DetallePedido::create(['id_pedido' => $pedido->id_pedido, 'id_producto' => $otro->id_producto, 'cantidad' => 1, 'precio_unitario_pactado' => 500]);

        $linea->update(['cantidad' => 5]);

        $this->assertEquals([5, 1], DetallePedido::orderBy('id_producto')->pluck('cantidad')->map(fn ($c) => (float) $c)->all());
        $this->assertCount(2, $pedido->detallesPedido);
    }

    public function test_historiales_son_de_solo_insercion(): void
    {
        $pedido = $this->crearPedido();
        $historial = HistorialEstadoPedido::create(['id_pedido' => $pedido->id_pedido, 'id_estado_pedido' => $pedido->id_estado_pedido]);

        $this->expectException(LogicException::class);
        $historial->update(['comentario' => 'cambio']);
    }

    public function test_historiales_no_se_borran(): void
    {
        $pedido = $this->crearPedido();
        $historial = HistorialEstadoPedido::create(['id_pedido' => $pedido->id_pedido, 'id_estado_pedido' => $pedido->id_estado_pedido]);

        $this->expectException(LogicException::class);
        $historial->delete();
    }

    public function test_usuario_guarda_la_contrasena_con_hash(): void
    {
        $usuario = $this->crearUsuario();

        $this->assertNotSame('secreto-de-prueba', $usuario->password_hash);
        $this->assertTrue(Hash::check('secreto-de-prueba', $usuario->getAuthPassword()));
        $this->assertArrayNotHasKey('password_hash', $usuario->toArray());
        $this->assertTrue($usuario->refresh()->activo);
        $this->assertNotNull($usuario->creado_en);
    }

    public function test_inventario_actualiza_su_marca_de_tiempo(): void
    {
        $inventario = $this->crearInventario($this->crearProducto());
        $antes = $inventario->refresh()->actualizado_en;

        $this->travel(5)->minutes();
        $inventario->update(['stock_disponible' => 20]);

        $this->assertTrue($inventario->refresh()->actualizado_en->greaterThan($antes));
        $this->assertSame($inventario->id_producto, Inventario::find($inventario->id_producto)->id_producto);
    }

    /**
     * @return list<Model>
     */
    private function modelos(): array
    {
        $modelos = [];

        foreach (glob(app_path('Models/*.php')) as $archivo) {
            $clase = 'App\\Models\\'.basename($archivo, '.php');
            $reflexion = new ReflectionClass($clase);

            if ($reflexion->isAbstract() || ! ($reflexion->isSubclassOf(Modelo::class) || $clase === Usuario::class)) {
                continue;
            }

            $modelos[] = new $clase;
        }

        return $modelos;
    }

    /**
     * @return array<string, Relation>
     */
    private function relaciones(Model $modelo): array
    {
        $relaciones = [];

        foreach ((new ReflectionClass($modelo))->getMethods(ReflectionMethod::IS_PUBLIC) as $metodo) {
            $tipo = $metodo->getReturnType()?->getName();

            if ($metodo->class === $modelo::class && $tipo && is_subclass_of($tipo, Relation::class)) {
                $relaciones[$metodo->name] = $modelo->{$metodo->name}();
            }
        }

        return $relaciones;
    }
}
