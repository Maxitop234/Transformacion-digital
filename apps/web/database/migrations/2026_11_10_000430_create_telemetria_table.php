<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `telemetria`: Lecturas periódicas de cada camión, insertadas por el simulador IoT. Sin particionar (ADR-0017).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetria', function (Blueprint $table) {
            $table->bigIncrements('id_telemetria')->generatedAs()->always();
            $table->string('camion_id', 20);
            $table->timestampTz('fecha_hora')->useCurrent();
            $table->float('temp_camara', 24);
            $table->double('lat');
            $table->double('long');
            $table->smallInteger('combustible_pct');
            $table->boolean('alerta_ruptura_frio')->default(false);

            $table->foreign('camion_id')->references('camion_id')->on('camion')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE telemetria ADD CONSTRAINT telemetria_temp_camara_check CHECK (temp_camara BETWEEN -60 AND 60)');
        DB::statement('ALTER TABLE telemetria ADD CONSTRAINT telemetria_lat_check CHECK (lat BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE telemetria ADD CONSTRAINT telemetria_long_check CHECK ("long" BETWEEN -180 AND 180)');
        DB::statement('ALTER TABLE telemetria ADD CONSTRAINT telemetria_combustible_pct_check CHECK (combustible_pct BETWEEN 0 AND 100)');
        DB::statement('CREATE INDEX telemetria_camion_id_fecha_hora_index ON telemetria (camion_id, fecha_hora DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetria');
    }
};
