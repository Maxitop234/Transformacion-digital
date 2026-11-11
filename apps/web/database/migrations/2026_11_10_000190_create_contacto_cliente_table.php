<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `contacto_cliente`: Personas de contacto de un cliente (un B2B puede tener varias).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacto_cliente', function (Blueprint $table) {
            $table->increments('id_contacto')->generatedAs()->always();
            $table->integer('id_cliente');
            $table->string('nombre', 100);
            $table->string('cargo', 80)->nullable();
            $table->string('email', 120);
            $table->string('telefono', 20)->nullable();
            $table->boolean('es_principal')->default(false);

            $table->foreign('id_cliente')->references('id_cliente')->on('cliente')->restrictOnDelete();
            $table->index('id_cliente');
        });

        DB::statement('CREATE UNIQUE INDEX contacto_cliente_principal_unique ON contacto_cliente (id_cliente) WHERE es_principal');
    }

    public function down(): void
    {
        Schema::dropIfExists('contacto_cliente');
    }
};
