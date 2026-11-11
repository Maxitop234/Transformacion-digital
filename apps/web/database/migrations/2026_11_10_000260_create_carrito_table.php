<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `carrito`: Carrito de compras del portal.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrito', function (Blueprint $table) {
            $table->increments('id_carrito')->generatedAs()->always();
            $table->integer('id_cliente');
            $table->string('estado', 15)->default('activo');
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('id_cliente')->references('id_cliente')->on('cliente')->restrictOnDelete();
            $table->index('id_cliente');
        });

        DB::statement("ALTER TABLE carrito ADD CONSTRAINT carrito_estado_check CHECK (estado IN ('activo', 'convertido', 'abandonado'))");
        DB::statement("CREATE UNIQUE INDEX carrito_activo_unique ON carrito (id_cliente) WHERE estado = 'activo'");
    }

    public function down(): void
    {
        Schema::dropIfExists('carrito');
    }
};
