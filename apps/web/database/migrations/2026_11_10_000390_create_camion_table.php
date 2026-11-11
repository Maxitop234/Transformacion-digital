<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `camion`: Flota de camiones frigoríficos; camion_id es el código que envía la telemetría.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camion', function (Blueprint $table) {
            $table->string('camion_id', 20);
            $table->string('patente', 10)->unique();
            $table->integer('capacidad_kg');
            $table->boolean('activo')->default(true);

            $table->primary('camion_id');
        });

        DB::statement('ALTER TABLE camion ADD CONSTRAINT camion_capacidad_kg_check CHECK (capacidad_kg > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('camion');
    }
};
