<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `usuario`: Credenciales de acceso al portal y al CRM (un login para todos los perfiles).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->increments('id_usuario')->generatedAs()->always();
            $table->integer('id_rol');
            $table->string('email', 120)->unique();
            $table->string('password_hash', 255);
            $table->boolean('activo')->default(true);
            $table->timestampTz('ultimo_acceso')->nullable();
            $table->timestampTz('creado_en')->useCurrent();

            $table->foreign('id_rol')->references('id_rol')->on('rol')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};
