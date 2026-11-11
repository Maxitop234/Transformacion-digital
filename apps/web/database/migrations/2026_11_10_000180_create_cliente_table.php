<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `cliente`: Cliente B2B/B2C o prospecto: entidad central que une ventas, CRM y despacho.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente', function (Blueprint $table) {
            $table->increments('id_cliente')->generatedAs()->always();
            $table->integer('id_usuario')->nullable()->unique();
            $table->integer('id_tipo_cliente');
            $table->integer('id_lista_precio');
            $table->integer('id_vendedor')->nullable();
            $table->string('rut', 12)->nullable()->unique();
            $table->string('nombre_razon_social', 150);
            $table->string('estado_cliente', 25)->default('prospecto');
            $table->timestampTz('fecha_registro')->useCurrent();
            $table->timestampTz('fecha_validacion')->nullable();

            $table->foreign('id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();
            $table->foreign('id_tipo_cliente')->references('id_tipo_cliente')->on('tipo_cliente')->restrictOnDelete();
            $table->foreign('id_lista_precio')->references('id_lista_precio')->on('lista_precio')->restrictOnDelete();
            $table->foreign('id_vendedor')->references('id_vendedor')->on('vendedor')->restrictOnDelete();
            $table->index('id_vendedor');
        });

        DB::statement("ALTER TABLE cliente ADD CONSTRAINT cliente_estado_cliente_check CHECK (estado_cliente IN ('prospecto', 'pendiente_validacion', 'activo', 'inactivo'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente');
    }
};
