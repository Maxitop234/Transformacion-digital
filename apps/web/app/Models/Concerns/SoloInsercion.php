<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Historiales de solo inserción (AGENTS.md, DATA_MODEL.md): una fila se crea
 * y nunca se actualiza ni se borra. Los permisos de base (T-005) lo refuerzan.
 */
trait SoloInsercion
{
    public static function bootSoloInsercion(): void
    {
        static::updating(function ($modelo): void {
            throw new LogicException(sprintf('La tabla %s es de solo inserción: no se actualiza.', $modelo->getTable()));
        });

        static::deleting(function ($modelo): void {
            throw new LogicException(sprintf('La tabla %s es de solo inserción: no se borra.', $modelo->getTable()));
        });
    }
}
