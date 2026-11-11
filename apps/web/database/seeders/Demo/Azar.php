<?php

namespace Database\Seeders\Demo;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Azar con semilla fija: la demo genera siempre los mismos datos.
 */
final class Azar
{
    private Randomizer $r;

    public function __construct(int $semilla)
    {
        $this->r = new Randomizer(new Mt19937($semilla));
    }

    public function entero(int $min, int $max): int
    {
        return $this->r->getInt($min, $max);
    }

    public function probabilidad(float $p): bool
    {
        return $this->r->nextFloat() < $p;
    }

    /**
     * @template T
     *
     * @param  array<array-key, T>  $opciones
     * @return T
     */
    public function uno(array $opciones): mixed
    {
        $opciones = array_values($opciones);

        return $opciones[$this->r->getInt(0, count($opciones) - 1)];
    }

    /**
     * @template T
     *
     * @param  array<array-key, T>  $opciones
     * @return list<T>
     */
    public function varios(array $opciones, int $cantidad): array
    {
        $mezcla = $this->r->shuffleArray(array_values($opciones));

        return array_slice($mezcla, 0, min($cantidad, count($mezcla)));
    }

    /**
     * Elige una clave según pesos relativos.
     *
     * @param  array<string, int>  $pesos
     */
    public function ponderado(array $pesos): string
    {
        $tiro = $this->r->getInt(1, array_sum($pesos));

        foreach ($pesos as $opcion => $peso) {
            $tiro -= $peso;

            if ($tiro <= 0) {
                return $opcion;
            }
        }

        return array_key_last($pesos);
    }
}
