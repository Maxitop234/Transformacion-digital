<?php

namespace App\Domain\Ventas;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * RN-06: la entrega se pide desde el siguiente día hábil. Se despacha de
 * lunes a sábado (mismo criterio que los datos de demo); el domingo no.
 * Los días se cuentan en la zona de negocio (`app.timezone`).
 */
final class CalendarioEntregas
{
    public function primeraFecha(?CarbonInterface $ahora = null): CarbonImmutable
    {
        $dia = CarbonImmutable::parse($ahora ?? now())->setTimezone(config('app.timezone'))->addDay()->startOfDay();

        return $dia->isSunday() ? $dia->addDay() : $dia;
    }

    public function admite(CarbonInterface $fecha, ?CarbonInterface $ahora = null): bool
    {
        $fecha = CarbonImmutable::parse($fecha->toDateString(), config('app.timezone'));

        return ! $fecha->isSunday() && $fecha->gte($this->primeraFecha($ahora));
    }
}
