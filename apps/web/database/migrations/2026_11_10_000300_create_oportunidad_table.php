<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `oportunidad`: Oportunidad de venta de un cliente o prospecto dentro del pipeline.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oportunidad', function (Blueprint $table) {
            $table->increments('id_oportunidad')->generatedAs()->always();
            $table->integer('id_cliente');
            $table->integer('id_vendedor');
            $table->integer('id_etapa');
            $table->string('titulo', 150);
            $table->string('origen', 15);
            $table->decimal('monto_estimado', 14, 2)->nullable();
            $table->timestampTz('fecha_creacion')->useCurrent();
            $table->date('fecha_cierre_estimada')->nullable();
            $table->timestampTz('fecha_cierre')->nullable();
            $table->string('motivo_perdida', 200)->nullable();

            $table->foreign('id_cliente')->references('id_cliente')->on('cliente')->restrictOnDelete();
            $table->foreign('id_vendedor')->references('id_vendedor')->on('vendedor')->restrictOnDelete();
            $table->foreign('id_etapa')->references('id_etapa')->on('etapa_pipeline')->restrictOnDelete();
            $table->index('id_cliente');
            $table->index('id_vendedor');
            $table->index('id_etapa');
        });

        DB::statement("ALTER TABLE oportunidad ADD CONSTRAINT oportunidad_origen_check CHECK (origen IN ('portal', 'vendedor', 'telefono', 'referido'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('oportunidad');
    }
};
