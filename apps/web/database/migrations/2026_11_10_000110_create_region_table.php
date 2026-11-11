<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `region`: Regiones de Chile donde Cofrial despacha.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('region', function (Blueprint $table) {
            $table->increments('id_region')->generatedAs()->always();
            $table->string('nombre', 60)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region');
    }
};
