<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostGIS para consultas espaciales sobre la flota (ADR-0007). Crear la
 * extensión exige superusuario: en local la crea infra/docker/db y en RDS el
 * usuario maestro (T-023); aquí `IF NOT EXISTS` no requiere privilegios si ya
 * existe. No se elimina al revertir porque es infraestructura compartida.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
    }

    public function down(): void
    {
        //
    }
};
