<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `cliente.consentimiento_datos_en`: cuándo el titular aceptó el tratamiento
 * de sus datos personales al registrarse en el portal (T-009, Ley 21.719,
 * SECURITY.md §1). Nulo en prospectos ingresados en el CRM y en clientes
 * anteriores a este cambio.
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->timestampTz('consentimiento_datos_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->dropColumn('consentimiento_datos_en');
        });
    }
};
