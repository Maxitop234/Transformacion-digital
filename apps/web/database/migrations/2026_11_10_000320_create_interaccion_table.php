<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `interaccion`: Toda comunicación con un cliente queda registrada aquí.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interaccion', function (Blueprint $table) {
            $table->bigIncrements('id_interaccion')->generatedAs()->always();
            $table->integer('id_cliente');
            $table->integer('id_vendedor');
            $table->integer('id_oportunidad')->nullable();
            $table->string('tipo', 15);
            $table->text('resumen');
            $table->timestampTz('fecha_hora')->useCurrent();

            $table->foreign('id_cliente')->references('id_cliente')->on('cliente')->restrictOnDelete();
            $table->foreign('id_vendedor')->references('id_vendedor')->on('vendedor')->restrictOnDelete();
            $table->foreign('id_oportunidad')->references('id_oportunidad')->on('oportunidad')->restrictOnDelete();
            $table->index('id_cliente');
            $table->index('id_vendedor');
        });

        DB::statement("ALTER TABLE interaccion ADD CONSTRAINT interaccion_tipo_check CHECK (tipo IN ('llamada', 'correo', 'reunion', 'visita', 'mensaje'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('interaccion');
    }
};
