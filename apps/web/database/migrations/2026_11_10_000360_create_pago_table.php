<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `pago`: Pagos asociados a un pedido (RN-05).
 *
 * Definición: docs/DATA_MODEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago', function (Blueprint $table) {
            $table->increments('id_pago')->generatedAs()->always();
            $table->integer('id_pedido');
            $table->decimal('monto', 12, 2);
            $table->string('metodo', 15);
            $table->string('estado', 15)->default('pendiente');
            $table->string('referencia_externa', 80)->nullable();
            $table->timestampTz('fecha_hora')->useCurrent();

            $table->foreign('id_pedido')->references('id_pedido')->on('pedido')->restrictOnDelete();
            $table->index('id_pedido');
        });

        DB::statement('ALTER TABLE pago ADD CONSTRAINT pago_monto_check CHECK (monto > 0)');
        DB::statement("ALTER TABLE pago ADD CONSTRAINT pago_metodo_check CHECK (metodo IN ('tarjeta', 'transferencia', 'credito_30d', 'efectivo'))");
        DB::statement("ALTER TABLE pago ADD CONSTRAINT pago_estado_check CHECK (estado IN ('pendiente', 'aprobado', 'rechazado', 'reembolsado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pago');
    }
};
