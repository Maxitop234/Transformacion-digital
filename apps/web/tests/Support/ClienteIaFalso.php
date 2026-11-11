<?php

namespace Tests\Support;

use App\Domain\Ia\ClienteIa;
use App\Domain\Ia\IaNoDisponible;

/**
 * IA falsa para los tests (ADR-0015): no gasta cuota. Devuelve las
 * respuestas encoladas en orden (o lanza `IaNoDisponible` si se pide que
 * falle) y guarda cada mensaje enviado para revisar qué salió del sistema.
 */
final class ClienteIaFalso implements ClienteIa
{
    /** @var list<array{sistema: string, usuario: string, esquema: string}> */
    public array $enviados = [];

    /** @var list<array<string, mixed>|\Throwable> */
    private array $respuestas = [];

    /** @param  array<string, mixed>  $respuesta */
    public function responder(array $respuesta): self
    {
        $this->respuestas[] = $respuesta;

        return $this;
    }

    public function fallar(string $motivo = 'API caída'): self
    {
        $this->respuestas[] = new IaNoDisponible($motivo);

        return $this;
    }

    public function completarJson(string $sistema, string $usuario, string $nombreEsquema, array $esquema): array
    {
        $this->enviados[] = ['sistema' => $sistema, 'usuario' => $usuario, 'esquema' => $nombreEsquema];
        $respuesta = array_shift($this->respuestas) ?? new IaNoDisponible('Sin respuesta preparada en el test.');

        if ($respuesta instanceof \Throwable) {
            throw $respuesta;
        }

        return $respuesta;
    }
}
