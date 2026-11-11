<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `etapa_pipeline`: Etapas del pipeline comercial.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etapa_pipeline', function (Blueprint $table) {
            $table->increments('id_etapa')->generatedAs()->always();
            $table->string('nombre', 40)->unique();
            $table->smallInteger('orden')->unique();
            $table->boolean('es_cierre')->default(false);
            $table->boolean('es_ganada')->default(false);
        });

        DB::statement('ALTER TABLE etapa_pipeline ADD CONSTRAINT etapa_pipeline_ganada_es_cierre_check CHECK (NOT es_ganada OR es_cierre)');
    }

    public function down(): void
    {
        Schema::dropIfExists('etapa_pipeline');
    }
};
