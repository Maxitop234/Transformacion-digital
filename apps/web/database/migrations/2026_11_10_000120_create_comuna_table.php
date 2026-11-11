<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `comuna`: Comunas de despacho, separadas de la región (3FN).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comuna', function (Blueprint $table) {
            $table->increments('id_comuna')->generatedAs()->always();
            $table->integer('id_region');
            $table->string('nombre', 80);

            $table->foreign('id_region')->references('id_region')->on('region')->restrictOnDelete();
            $table->unique(['id_region', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comuna');
    }
};
