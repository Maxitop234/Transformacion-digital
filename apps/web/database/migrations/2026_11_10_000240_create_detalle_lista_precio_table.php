<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `detalle_lista_precio`: Precio de cada producto en cada lista (N:M lista–producto).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_lista_precio', function (Blueprint $table) {
            $table->integer('id_lista_precio');
            $table->integer('id_producto');
            $table->decimal('precio_unitario', 12, 2);

            $table->primary(['id_lista_precio', 'id_producto']);
            $table->foreign('id_lista_precio')->references('id_lista_precio')->on('lista_precio')->restrictOnDelete();
            $table->foreign('id_producto')->references('id_producto')->on('producto')->restrictOnDelete();
            $table->index('id_producto');
        });

        DB::statement('ALTER TABLE detalle_lista_precio ADD CONSTRAINT detalle_lista_precio_precio_unitario_check CHECK (precio_unitario >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_lista_precio');
    }
};
