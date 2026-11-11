<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `historial_estado_pedido`: Bitácora de cada cambio de estado del pedido (solo inserción).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_estado_pedido', function (Blueprint $table) {
            $table->bigIncrements('id_historial')->generatedAs()->always();
            $table->integer('id_pedido');
            $table->integer('id_estado_pedido');
            $table->integer('id_usuario')->nullable();
            $table->timestampTz('fecha_hora')->useCurrent();
            $table->string('comentario', 300)->nullable();

            $table->foreign('id_pedido')->references('id_pedido')->on('pedido')->restrictOnDelete();
            $table->foreign('id_estado_pedido')->references('id_estado_pedido')->on('estado_pedido')->restrictOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();
            $table->index('id_pedido');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_estado_pedido');
    }
};
