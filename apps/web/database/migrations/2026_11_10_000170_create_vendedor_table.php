<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `vendedor`: Ejecutivos comerciales que operan el CRM.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendedor', function (Blueprint $table) {
            $table->increments('id_vendedor')->generatedAs()->always();
            $table->integer('id_usuario')->unique();
            $table->string('nombre', 100);
            $table->string('telefono', 20)->nullable();
            $table->boolean('activo')->default(true);

            $table->foreign('id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendedor');
    }
};
