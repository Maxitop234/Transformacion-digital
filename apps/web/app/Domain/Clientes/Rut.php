<?php

namespace App\Domain\Clientes;

use InvalidArgumentException;

/**
 * RUT chileno con dígito verificador (módulo 11).
 *
 * Se guarda normalizado sin puntos y con guion: `12345678-5`, `7654321-K`.
 */
final class Rut
{
    private function __construct(
        public readonly int $numero,
        public readonly string $dv,
    ) {}

    public static function desdeNumero(int $numero): self
    {
        if ($numero < 1 || $numero > 99_999_999) {
            throw new InvalidArgumentException('El número de RUT debe estar entre 1 y 99.999.999.');
        }

        return new self($numero, self::calcularDv($numero));
    }

    public static function desdeTexto(string $rut): self
    {
        $limpio = strtoupper(str_replace(['.', ' '], '', trim($rut)));

        if (! preg_match('/^(\d{1,8})-?([\dK])$/', $limpio, $m)) {
            throw new InvalidArgumentException('Formato de RUT inválido.');
        }

        $rut = self::desdeNumero((int) $m[1]);

        if ($rut->dv !== $m[2]) {
            throw new InvalidArgumentException('El dígito verificador del RUT no es válido.');
        }

        return $rut;
    }

    public static function esValido(string $rut): bool
    {
        try {
            self::desdeTexto($rut);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function calcularDv(int $numero): string
    {
        $suma = 0;
        $factor = 2;

        for ($n = $numero; $n > 0; $n = intdiv($n, 10)) {
            $suma += ($n % 10) * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $resto = 11 - ($suma % 11);

        return match ($resto) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };
    }

    /** Forma normalizada para guardar: sin puntos, con guion. */
    public function normalizado(): string
    {
        return "{$this->numero}-{$this->dv}";
    }

    /** Forma de presentación: con puntos y guion. */
    public function formateado(): string
    {
        return number_format($this->numero, 0, '', '.')."-{$this->dv}";
    }

    public function __toString(): string
    {
        return $this->normalizado();
    }
}
