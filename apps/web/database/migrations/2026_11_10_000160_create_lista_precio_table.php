<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `lista_precio`: Listas de precios: una pública (B2C) y una o más institucionales (B2B).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_precio', function (Blueprint $table) {
            $table->increments('id_lista_precio')->generatedAs()->always();
            $table->string('nombre', 80)->unique();
            $table->date('vigente_desde')->default(DB::raw('CURRENT_DATE'));
            $table->date('vigente_hasta')->nullable();
            $table->boolean('activa')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_precio');
    }
};
