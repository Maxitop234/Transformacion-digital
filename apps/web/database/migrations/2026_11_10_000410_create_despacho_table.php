<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `despacho`: Viaje de un camión con un conductor; agrupa varios pedidos en una ruta.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despacho', function (Blueprint $table) {
            $table->increments('id_despacho')->generatedAs()->always();
            $table->string('camion_id', 20);
            $table->integer('id_conductor');
            $table->string('estado', 15)->default('programado');
            $table->date('fecha_programada');
            $table->timestampTz('salida_real')->nullable();
            $table->timestampTz('llegada_real')->nullable();

            $table->foreign('camion_id')->references('camion_id')->on('camion')->restrictOnDelete();
            $table->foreign('id_conductor')->references('id_conductor')->on('conductor')->restrictOnDelete();
            $table->index(['camion_id', 'estado']);
            $table->index('id_conductor');
        });

        DB::statement("ALTER TABLE despacho ADD CONSTRAINT despacho_estado_check CHECK (estado IN ('programado', 'en_ruta', 'finalizado', 'cancelado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('despacho');
    }
};
