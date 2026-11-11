<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `item_carrito`: Productos dentro de un carrito; el precio se calcula con la lista del cliente.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_carrito', function (Blueprint $table) {
            $table->integer('id_carrito');
            $table->integer('id_producto');
            $table->decimal('cantidad', 12, 2);

            $table->primary(['id_carrito', 'id_producto']);
            $table->foreign('id_carrito')->references('id_carrito')->on('carrito')->restrictOnDelete();
            $table->foreign('id_producto')->references('id_producto')->on('producto')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE item_carrito ADD CONSTRAINT item_carrito_cantidad_check CHECK (cantidad > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_carrito');
    }
};
