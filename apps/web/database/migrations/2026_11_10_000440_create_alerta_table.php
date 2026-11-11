<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `alerta`: Alerta operativa generada por una lectura crítica y gestionada hasta su resolución.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta', function (Blueprint $table) {
            $table->bigIncrements('id_alerta')->generatedAs()->always();
            $table->bigInteger('id_telemetria');
            $table->integer('id_usuario_resuelve')->nullable();
            $table->string('tipo', 20);
            $table->string('severidad', 10);
            $table->string('estado', 15)->default('abierta');
            $table->string('accion_correctiva', 300)->nullable();
            $table->timestampTz('fecha_resolucion')->nullable();

            $table->foreign('id_telemetria')->references('id_telemetria')->on('telemetria')->restrictOnDelete();
            $table->foreign('id_usuario_resuelve')->references('id_usuario')->on('usuario')->restrictOnDelete();
            $table->index('id_telemetria');
            $table->index('estado');
        });

        DB::statement("ALTER TABLE alerta ADD CONSTRAINT alerta_tipo_check CHECK (tipo IN ('ruptura_frio', 'combustible_bajo'))");
        DB::statement("ALTER TABLE alerta ADD CONSTRAINT alerta_severidad_check CHECK (severidad IN ('media', 'alta', 'critica'))");
        DB::statement("ALTER TABLE alerta ADD CONSTRAINT alerta_estado_check CHECK (estado IN ('abierta', 'reconocida', 'resuelta'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta');
    }
};
