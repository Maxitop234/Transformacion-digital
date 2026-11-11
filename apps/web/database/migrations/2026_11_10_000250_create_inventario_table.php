<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `inventario`: Existencias actuales por producto (una sola bodega). Disponible para venta = stock_disponible - stock_reservado (RN-04).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario', function (Blueprint $table) {
            $table->integer('id_producto');
            $table->decimal('stock_disponible', 12, 2)->default(0);
            $table->decimal('stock_reservado', 12, 2)->default(0);
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->primary('id_producto');
            $table->foreign('id_producto')->references('id_producto')->on('producto')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE inventario ADD CONSTRAINT inventario_stock_disponible_check CHECK (stock_disponible >= 0)');
        DB::statement('ALTER TABLE inventario ADD CONSTRAINT inventario_stock_reservado_check CHECK (stock_reservado >= 0)');
        DB::statement('ALTER TABLE inventario ADD CONSTRAINT inventario_reservado_menor_disponible_check CHECK (stock_reservado <= stock_disponible)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario');
    }
};
