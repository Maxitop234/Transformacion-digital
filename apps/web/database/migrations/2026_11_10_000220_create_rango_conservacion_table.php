<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `rango_conservacion`: Rangos de temperatura de conservación; de aquí sale el umbral de ruptura de frío (RN-07).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rango_conservacion', function (Blueprint $table) {
            $table->increments('id_rango')->generatedAs()->always();
            $table->string('nombre', 40)->unique();
            $table->decimal('temp_min_c', 4, 1);
            $table->decimal('temp_max_c', 4, 1);
        });

        DB::statement('ALTER TABLE rango_conservacion ADD CONSTRAINT rango_conservacion_temperaturas_check CHECK (temp_max_c > temp_min_c)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rango_conservacion');
    }
};
