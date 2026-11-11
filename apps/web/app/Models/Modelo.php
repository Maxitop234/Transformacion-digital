<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base de los modelos del MER (ADR-0016).
 *
 * Las tablas no siguen las convenciones de Eloquent: cada modelo declara su
 * `$table` y su `$primaryKey`. Las fechas son TIMESTAMPTZ y se guardan con
 * su desfase horario para no depender de la zona de la sesión.
 */
abstract class Modelo extends Model
{
    /**
     * Las tablas no tienen `created_at`/`updated_at`; las fechas de alta las
     * pone la base (`DEFAULT now()`). Los modelos con `actualizado_en` lo
     * activan y renombran las columnas.
     */
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:sP';
}
