<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `despacho_pedido`: Pedidos transportados en un despacho y el resultado de cada entrega.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despacho_pedido', function (Blueprint $table) {
            $table->integer('id_despacho');
            $table->integer('id_pedido');
            $table->smallInteger('orden_parada');
            $table->string('estado_entrega', 25)->default('pendiente');
            $table->timestampTz('fecha_entrega')->nullable();
            $table->string('motivo_falla', 200)->nullable();

            $table->primary(['id_despacho', 'id_pedido']);
            $table->foreign('id_despacho')->references('id_despacho')->on('despacho')->restrictOnDelete();
            $table->foreign('id_pedido')->references('id_pedido')->on('pedido')->restrictOnDelete();
            $table->index('id_pedido');
        });

        DB::statement('ALTER TABLE despacho_pedido ADD CONSTRAINT despacho_pedido_orden_parada_check CHECK (orden_parada > 0)');
        DB::statement("ALTER TABLE despacho_pedido ADD CONSTRAINT despacho_pedido_estado_entrega_check CHECK (estado_entrega IN ('pendiente', 'entregado', 'fallido', 'rechazado_temperatura'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('despacho_pedido');
    }
};
