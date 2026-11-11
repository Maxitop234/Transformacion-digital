<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `tipo_cliente`: Perfiles comerciales: B2B institucional y B2C público general.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_cliente', function (Blueprint $table) {
            $table->increments('id_tipo_cliente')->generatedAs()->always();
            $table->string('codigo', 3)->unique();
            $table->string('nombre', 60);
        });

        DB::statement("ALTER TABLE tipo_cliente ADD CONSTRAINT tipo_cliente_codigo_check CHECK (codigo IN ('B2B', 'B2C'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_cliente');
    }
};
