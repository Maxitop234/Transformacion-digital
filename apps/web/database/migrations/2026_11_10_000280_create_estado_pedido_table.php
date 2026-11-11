<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `estado_pedido`: Catálogo de estados del pedido.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_pedido', function (Blueprint $table) {
            $table->increments('id_estado_pedido')->generatedAs()->always();
            $table->string('nombre', 30)->unique();
            $table->smallInteger('orden')->unique();
            $table->boolean('es_final')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_pedido');
    }
};
