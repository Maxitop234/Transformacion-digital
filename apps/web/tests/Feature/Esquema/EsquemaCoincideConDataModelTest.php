<?php

namespace Tests\Feature\Esquema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * docs/DATA_MODEL.md es el contrato compartido con el simulador y el BI
 * (ADR-0016): columnas, tipos, nulidad y listas de valores deben coincidir
 * exactamente con lo que crean las migraciones.
 */
class EsquemaCoincideConDataModelTest extends TestCase
{
    use RefreshDatabase;

    private const TIPOS = [
        'INT' => 'integer',
        'BIGINT' => 'bigint',
        'SMALLINT' => 'smallint',
        'TEXT' => 'text',
        'BOOLEAN' => 'boolean',
        'TIMESTAMPTZ' => 'timestamp with time zone',
        'DATE' => 'date',
        'TIME' => 'time without time zone',
        'DOUBLE PRECISION' => 'double precision',
        'REAL' => 'real',
    ];

    public function test_las_tablas_y_columnas_coinciden_con_data_model(): void
    {
        $documento = $this->leerDataModel();

        $this->assertSame(count(TablasTest::TABLAS_DE_NEGOCIO), count($documento), 'DATA_MODEL.md debe describir las 34 tablas');

        foreach ($documento as $tabla => $columnas) {
            $enBase = $this->columnasEnBase($tabla);

            $this->assertSame(array_keys($columnas), array_keys($enBase), "{$tabla}: columnas u orden distintos");

            foreach ($columnas as $columna => $esperado) {
                $real = $enBase[$columna];
                $this->assertSame($esperado['tipo'], $real['tipo'], "{$tabla}.{$columna}: tipo");
                $this->assertSame($esperado['nulo'], $real['nulo'], "{$tabla}.{$columna}: nulidad");

                if ($real['lista'] !== null) {
                    $this->assertEqualsCanonicalizing($real['lista'], $esperado['valores'], "{$tabla}.{$columna}: lista de valores");
                }
            }
        }
    }

    /**
     * @return array<string, array<string, array{tipo: string, nulo: bool, valores: list<string>}>>
     */
    private function leerDataModel(): array
    {
        $ruta = base_path('../../docs/DATA_MODEL.md');

        if (! is_file($ruta)) {
            $this->markTestSkipped('docs/DATA_MODEL.md no está disponible (p. ej. dentro del contenedor, que solo monta apps/web).');
        }

        $tablas = [];
        $actual = null;

        foreach (file($ruta, FILE_IGNORE_NEW_LINES) as $linea) {
            if (preg_match('/^### `([a-z_]+)`/', $linea, $m)) {
                $actual = $m[1];
                $tablas[$actual] = [];

                continue;
            }

            if (str_starts_with($linea, '## ')) {
                $actual = null;
            }

            if ($actual && preg_match('/^\| `([a-z_]+)` \| ([^|]+)\|[^|]*\|(.*)\|$/', $linea, $m)) {
                $tipo = trim($m[2]);
                $nulo = str_ends_with($tipo, '?');
                preg_match_all('/`([A-Za-z0-9_]+)`/', $m[3], $valores);

                $tablas[$actual][$m[1]] = [
                    'tipo' => $this->normalizarTipo(rtrim($tipo, '?')),
                    'nulo' => $nulo,
                    'valores' => array_values(array_unique($valores[1])),
                ];
            }
        }

        return $tablas;
    }

    private function normalizarTipo(string $tipo): string
    {
        if (preg_match('/^VARCHAR\((\d+)\)$/', $tipo, $m)) {
            return "character varying({$m[1]})";
        }

        if (preg_match('/^NUMERIC\((\d+),(\d+)\)$/', $tipo, $m)) {
            return "numeric({$m[1]},{$m[2]})";
        }

        return self::TIPOS[$tipo] ?? "desconocido: {$tipo}";
    }

    /**
     * @return array<string, array{tipo: string, nulo: bool, lista: ?list<string>}>
     */
    private function columnasEnBase(string $tabla): array
    {
        $listas = [];

        foreach (DB::select("select pg_get_constraintdef(c.oid) as definicion from pg_constraint c where c.conrelid = ?::regclass and c.contype = 'c'", [$tabla]) as $check) {
            // CHECK (((estado)::text = ANY ((ARRAY['activo'::character varying, ...])::text[])))
            if (preg_match('/^CHECK \(\(\(?"?([a-z_]+)"?\)?(?:::text)? = ANY \(/', $check->definicion, $m)) {
                preg_match_all("/'([^']*)'::/", $check->definicion, $valores);
                $listas[$m[1]] = $valores[1];
            }
        }

        $columnas = [];

        foreach (DB::select('select column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, is_nullable from information_schema.columns where table_schema = ? and table_name = ? order by ordinal_position', ['public', $tabla]) as $c) {
            $tipo = match ($c->data_type) {
                'character varying' => "character varying({$c->character_maximum_length})",
                'numeric' => "numeric({$c->numeric_precision},{$c->numeric_scale})",
                default => $c->data_type,
            };

            $columnas[$c->column_name] = [
                'tipo' => $tipo,
                'nulo' => $c->is_nullable === 'YES',
                'lista' => $listas[$c->column_name] ?? null,
            ];
        }

        return $columnas;
    }
}
