<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `historial_etapa`: Cada cambio de etapa de una oportunidad (solo inserción).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_etapa', function (Blueprint $table) {
            $table->bigIncrements('id_historial')->generatedAs()->always();
            $table->integer('id_oportunidad');
            $table->integer('id_etapa');
            $table->integer('id_usuario')->nullable();
            $table->timestampTz('fecha_hora')->useCurrent();
            $table->string('comentario', 300)->nullable();

            $table->foreign('id_oportunidad')->references('id_oportunidad')->on('oportunidad')->restrictOnDelete();
            $table->foreign('id_etapa')->references('id_etapa')->on('etapa_pipeline')->restrictOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();
            $table->index('id_oportunidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_etapa');
    }
};
