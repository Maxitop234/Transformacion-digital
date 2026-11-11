<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `rol`: Perfiles de acceso (administrador, vendedor, despachador, cliente_b2b, cliente_b2c).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol', function (Blueprint $table) {
            $table->increments('id_rol')->generatedAs()->always();
            $table->string('nombre', 30)->unique();
            $table->string('descripcion', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol');
    }
};
