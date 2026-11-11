<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `movimiento_inventario`: Bitácora de cada cambio de stock (solo inserción).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimiento_inventario', function (Blueprint $table) {
            $table->bigIncrements('id_movimiento')->generatedAs()->always();
            $table->integer('id_producto');
            $table->integer('id_pedido')->nullable();
            $table->integer('id_usuario');
            $table->string('tipo', 15);
            $table->decimal('cantidad', 12, 2);
            $table->timestampTz('fecha_hora')->useCurrent();

            $table->foreign('id_producto')->references('id_producto')->on('producto')->restrictOnDelete();
            $table->foreign('id_pedido')->references('id_pedido')->on('pedido')->restrictOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();
            $table->index(['id_producto', 'fecha_hora']);
            $table->index('id_pedido');
        });

        DB::statement("ALTER TABLE movimiento_inventario ADD CONSTRAINT movimiento_inventario_tipo_check CHECK (tipo IN ('ingreso', 'reserva', 'liberacion', 'salida', 'ajuste', 'merma'))");
        DB::statement('ALTER TABLE movimiento_inventario ADD CONSTRAINT movimiento_inventario_cantidad_check CHECK (cantidad <> 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento_inventario');
    }
};
