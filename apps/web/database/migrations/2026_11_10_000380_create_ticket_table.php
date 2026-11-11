<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ticket`: Requerimientos y reclamos de clientes; la IA los clasifica (T-021).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket', function (Blueprint $table) {
            $table->increments('id_ticket')->generatedAs()->always();
            $table->integer('id_cliente');
            $table->integer('id_pedido')->nullable();
            $table->integer('id_vendedor')->nullable();
            $table->string('asunto', 150);
            $table->text('descripcion');
            $table->string('categoria', 15)->default('sin_clasificar');
            $table->string('prioridad', 10)->default('media');
            $table->string('estado', 15)->default('abierto');
            $table->boolean('clasificado_por_ia')->default(false);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('cerrado_en')->nullable();

            $table->foreign('id_cliente')->references('id_cliente')->on('cliente')->restrictOnDelete();
            $table->foreign('id_pedido')->references('id_pedido')->on('pedido')->restrictOnDelete();
            $table->foreign('id_vendedor')->references('id_vendedor')->on('vendedor')->restrictOnDelete();
            $table->index('id_cliente');
            $table->index('id_vendedor');
            $table->index('estado');
        });

        DB::statement("ALTER TABLE ticket ADD CONSTRAINT ticket_categoria_check CHECK (categoria IN ('sin_clasificar', 'consulta', 'reclamo', 'cambio_pedido', 'cotizacion', 'otro'))");
        DB::statement("ALTER TABLE ticket ADD CONSTRAINT ticket_prioridad_check CHECK (prioridad IN ('baja', 'media', 'alta'))");
        DB::statement("ALTER TABLE ticket ADD CONSTRAINT ticket_estado_check CHECK (estado IN ('abierto', 'en_proceso', 'resuelto', 'cerrado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket');
    }
};
