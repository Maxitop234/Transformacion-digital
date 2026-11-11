<?php

namespace Tests\Feature\Esquema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TablasTest extends TestCase
{
    use RefreshDatabase;

    /** Las 34 tablas de negocio de docs/DATA_MODEL.md. */
    public const TABLAS_DE_NEGOCIO = [
        // Clientes y accesos (10)
        'region', 'comuna', 'rol', 'usuario', 'tipo_cliente', 'lista_precio', 'vendedor', 'cliente', 'contacto_cliente', 'direccion_despacho',
        // Catálogo e inventario (6)
        'categoria', 'rango_conservacion', 'producto', 'detalle_lista_precio', 'inventario', 'movimiento_inventario',
        // Ventas (7)
        'carrito', 'item_carrito', 'estado_pedido', 'pedido', 'detalle_pedido', 'historial_estado_pedido', 'pago',
        // CRM comercial (5)
        'etapa_pipeline', 'oportunidad', 'historial_etapa', 'interaccion', 'ticket',
        // Logística e IoT (6)
        'camion', 'conductor', 'despacho', 'despacho_pedido', 'telemetria', 'alerta',
    ];

    /** Tablas que crean Laravel y PostGIS, fuera del MER. */
    private const TABLAS_DE_SOPORTE = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'spatial_ref_sys',
    ];

    public function test_existen_las_34_tablas_de_negocio(): void
    {
        $this->assertCount(34, self::TABLAS_DE_NEGOCIO);

        foreach (self::TABLAS_DE_NEGOCIO as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}");
        }
    }

    public function test_no_hay_tablas_fuera_del_modelo(): void
    {
        $existentes = collect(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"))
            ->pluck('table_name')
            ->all();

        $sobrantes = array_diff($existentes, self::TABLAS_DE_NEGOCIO, self::TABLAS_DE_SOPORTE);

        $this->assertSame([], array_values($sobrantes), 'Tablas que no están en DATA_MODEL.md');
    }

    public function test_postgis_esta_habilitado(): void
    {
        $this->assertNotEmpty(DB::selectOne('select postgis_version() as version')->version);
    }

    public function test_telemetria_tiene_indice_por_camion_y_fecha_descendente(): void
    {
        $definicion = DB::selectOne("select indexdef from pg_indexes where tablename = 'telemetria' and indexname = 'telemetria_camion_id_fecha_hora_index'");

        $this->assertNotNull($definicion);
        $this->assertStringContainsString('(camion_id, fecha_hora DESC)', $definicion->indexdef);
    }

    public function test_las_claves_numericas_son_identity_generated_always(): void
    {
        $sinIdentity = DB::select(<<<'SQL'
            select c.table_name, c.column_name
            from information_schema.table_constraints tc
            join information_schema.key_column_usage c
              on c.constraint_name = tc.constraint_name and c.table_name = tc.table_name
            join information_schema.columns col
              on col.table_name = c.table_name and col.column_name = c.column_name
            where tc.constraint_type = 'PRIMARY KEY'
              and tc.table_schema = 'public'
              and col.data_type in ('integer', 'bigint')
              and (select count(*) from information_schema.key_column_usage k where k.constraint_name = tc.constraint_name) = 1
              and c.table_name <> 'inventario'
              and c.table_name = any (?)
              and coalesce(col.identity_generation, '') <> 'ALWAYS'
            SQL, ['{'.implode(',', self::TABLAS_DE_NEGOCIO).'}']);

        $this->assertSame([], $sinIdentity);
    }
}
