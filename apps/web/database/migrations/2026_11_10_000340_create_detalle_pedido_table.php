<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `detalle_pedido`: Líneas del pedido con el precio pactado al comprar (RN-01).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_pedido', function (Blueprint $table) {
            $table->integer('id_pedido');
            $table->integer('id_producto');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario_pactado', 12, 2);

            $table->primary(['id_pedido', 'id_producto']);
            $table->foreign('id_pedido')->references('id_pedido')->on('pedido')->restrictOnDelete();
            $table->foreign('id_producto')->references('id_producto')->on('producto')->restrictOnDelete();
            $table->index('id_producto');
        });

        DB::statement('ALTER TABLE detalle_pedido ADD CONSTRAINT detalle_pedido_cantidad_check CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE detalle_pedido ADD CONSTRAINT detalle_pedido_precio_unitario_pactado_check CHECK (precio_unitario_pactado >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_pedido');
    }
};
