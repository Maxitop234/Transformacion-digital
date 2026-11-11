<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `pedido`: Cabecera del pedido; el cliente se obtiene desde la dirección de despacho (3FN).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido', function (Blueprint $table) {
            $table->increments('id_pedido')->generatedAs()->always();
            $table->integer('id_direccion');
            $table->integer('id_estado_pedido');
            $table->integer('id_carrito')->nullable()->unique();
            $table->integer('id_oportunidad')->nullable();
            $table->integer('id_vendedor_registra')->nullable();
            $table->string('canal', 10);
            $table->timestampTz('fecha_pedido')->useCurrent();
            $table->date('fecha_entrega_solicitada')->nullable();
            $table->string('observaciones', 300)->nullable();

            $table->foreign('id_direccion')->references('id_direccion')->on('direccion_despacho')->restrictOnDelete();
            $table->foreign('id_estado_pedido')->references('id_estado_pedido')->on('estado_pedido')->restrictOnDelete();
            $table->foreign('id_carrito')->references('id_carrito')->on('carrito')->restrictOnDelete();
            $table->foreign('id_oportunidad')->references('id_oportunidad')->on('oportunidad')->restrictOnDelete();
            $table->foreign('id_vendedor_registra')->references('id_vendedor')->on('vendedor')->restrictOnDelete();
            $table->index('id_direccion');
            $table->index('id_estado_pedido');
            $table->index('fecha_pedido');
        });

        DB::statement("ALTER TABLE pedido ADD CONSTRAINT pedido_canal_check CHECK (canal IN ('portal', 'crm'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido');
    }
};
