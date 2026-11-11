<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `conductor`: Conductores de la flota (datos personales: nunca se muestran al cliente).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conductor', function (Blueprint $table) {
            $table->increments('id_conductor')->generatedAs()->always();
            $table->string('rut', 12)->unique();
            $table->string('nombre', 100);
            $table->string('telefono', 20)->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conductor');
    }
};
