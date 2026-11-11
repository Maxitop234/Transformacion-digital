<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `producto`: Productos refrigerados y congelados que se venden y despachan.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto', function (Blueprint $table) {
            $table->increments('id_producto')->generatedAs()->always();
            $table->integer('id_categoria');
            $table->integer('id_rango');
            $table->string('sku', 30)->unique();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->string('unidad_medida', 10);
            $table->boolean('activo')->default(true);

            $table->foreign('id_categoria')->references('id_categoria')->on('categoria')->restrictOnDelete();
            $table->foreign('id_rango')->references('id_rango')->on('rango_conservacion')->restrictOnDelete();
            $table->index('id_categoria');
        });

        DB::statement("ALTER TABLE producto ADD CONSTRAINT producto_unidad_medida_check CHECK (unidad_medida IN ('kg', 'un', 'caja'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('producto');
    }
};
