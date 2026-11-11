<?php

namespace Tests\Unit;

use Dotenv\Dotenv;
use Tests\TestCase;

class ConfiguracionTest extends TestCase
{
    public function test_zona_horaria_y_localizacion_de_negocio(): void
    {
        $this->assertSame('America/Santiago', config('app.timezone'));
        $this->assertSame('America/Santiago', date_default_timezone_get());
        $this->assertSame('es', app()->getLocale());
        $this->assertSame('El campo rut es obligatorio.', __('validation.required', ['attribute' => 'rut']));
    }

    public function test_conexion_pgsql_lee_sslmode_del_entorno(): void
    {
        $this->assertSame('pgsql', config('database.connections.pgsql.driver'));
        $this->assertArrayHasKey('sslmode', config('database.connections.pgsql'));
    }

    public function test_sesiones_cache_y_colas_no_usan_disco_local(): void
    {
        // Sin estado en la instancia (ADR-0005): lo que se despliega y los
        // valores por defecto de config/ apuntan a la base, nunca a `file`.
        foreach ($this->archivosEnvExample() as $archivo) {
            $env = Dotenv::parse(file_get_contents($archivo));

            $this->assertSame('database', $env['SESSION_DRIVER'], $archivo);
            $this->assertSame('database', $env['CACHE_STORE'], $archivo);
            $this->assertSame('database', $env['QUEUE_CONNECTION'], $archivo);
            $this->assertSame('cache', $env['APP_MAINTENANCE_DRIVER'], $archivo);
        }

        $this->assertStringContainsString("env('SESSION_DRIVER', 'database')", file_get_contents(config_path('session.php')));
        $this->assertStringContainsString("env('CACHE_STORE', 'database')", file_get_contents(config_path('cache.php')));
        $this->assertStringContainsString("env('QUEUE_CONNECTION', 'database')", file_get_contents(config_path('queue.php')));
        $this->assertStringContainsString("env('APP_MAINTENANCE_DRIVER', 'cache')", file_get_contents(config_path('app.php')));
    }

    public function test_env_example_no_deja_comentarios_en_variables_vacias(): void
    {
        // Docker Compose toma `VAR=   # comentario` como valor; el comentario va arriba.
        foreach ($this->archivosEnvExample() as $archivo) {
            $this->assertDoesNotMatchRegularExpression('/^[A-Z_]+=[ \t]+#/m', file_get_contents($archivo), $archivo);
        }
    }

    /**
     * `.env.example` de apps/web y, si el repositorio completo está disponible
     * (no dentro del contenedor, que solo monta apps/web), el de la raíz.
     *
     * @return list<string>
     */
    private function archivosEnvExample(): array
    {
        return array_values(array_filter(
            [base_path('.env.example'), base_path('../../.env.example')],
            'is_file',
        ));
    }
}
