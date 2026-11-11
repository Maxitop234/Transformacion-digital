<?php

namespace App\Domain\Logistica;

use App\Models\Alerta;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierra una alerta de frío con su acción correctiva, obligatoria
 * (DATA_MODEL.md `alerta`). Queda quién la resolvió y cuándo; con eso el BI
 * calcula los minutos hasta resolver. Si el camión sigue fuera de rango, el
 * simulador abrirá una alerta nueva en la lectura siguiente.
 */
final class ResolverAlerta
{
    public function __invoke(Alerta $alerta, string $accion, Usuario $actor): Alerta
    {
        $accion = trim($accion);

        if (mb_strlen($accion) < 10) {
            throw ValidationException::withMessages(['accion_correctiva' => 'Describe la acción correctiva (al menos 10 caracteres).']);
        }

        return DB::transaction(function () use ($alerta, $accion, $actor) {
            $alerta = Alerta::whereKey($alerta->id_alerta)->lockForUpdate()->firstOrFail();

            if ($alerta->estado === 'resuelta') {
                throw new ConflictoLogistica('La alerta ya está resuelta.');
            }

            $alerta->update([
                'estado' => 'resuelta',
                'accion_correctiva' => mb_substr($accion, 0, 300),
                'id_usuario_resuelve' => $actor->id_usuario,
                'fecha_resolucion' => now(),
            ]);

            return $alerta;
        });
    }
}
