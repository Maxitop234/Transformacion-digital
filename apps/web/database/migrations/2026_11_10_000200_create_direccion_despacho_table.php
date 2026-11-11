<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `direccion_despacho`: Puntos de entrega de un cliente (B2B: varias sucursales).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direccion_despacho', function (Blueprint $table) {
            $table->increments('id_direccion')->generatedAs()->always();
            $table->integer('id_cliente');
            $table->integer('id_comuna');
            $table->string('alias', 60);
            $table->string('calle', 150);
            $table->string('referencia', 200)->nullable();
            $table->double('lat')->nullable();
            $table->double('long')->nullable();
            $table->time('ventana_inicio')->nullable();
            $table->time('ventana_fin')->nullable();
            $table->boolean('activa')->default(true);

            $table->foreign('id_cliente')->references('id_cliente')->on('cliente')->restrictOnDelete();
            $table->foreign('id_comuna')->references('id_comuna')->on('comuna')->restrictOnDelete();
            $table->index('id_cliente');
        });

        DB::statement('ALTER TABLE direccion_despacho ADD CONSTRAINT direccion_despacho_lat_check CHECK (lat BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE direccion_despacho ADD CONSTRAINT direccion_despacho_long_check CHECK ("long" BETWEEN -180 AND 180)');
        DB::statement('ALTER TABLE direccion_despacho ADD CONSTRAINT direccion_despacho_ventana_check CHECK (ventana_fin > ventana_inicio)');
    }

    public function down(): void
    {
        Schema::dropIfExists('direccion_despacho');
    }
};
