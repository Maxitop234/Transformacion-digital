<?php

namespace App\Domain\Ventas;

/**
 * Aritmética de dinero y cantidades del carrito y del pedido.
 *
 * Precios y cantidades son NUMERIC(12,2): se opera en centésimas enteras
 * para no arrastrar errores de coma flotante. Los montos de presentación y
 * de pago son pesos enteros:
 *
 * - subtotal = precio × cantidad, redondeado al peso;
 * - neto = Σ subtotales; IVA = neto × `negocio.iva_pct`, al peso;
 * - total = neto + IVA.
 */
final class Montos
{
    public static function subtotalNeto(string $precio, string $cantidad): int
    {
        $diezMilesimas = self::centesimas($precio) * self::centesimas($cantidad);

        return intdiv($diezMilesimas + 5000, 10000);
    }

    /**
     * Neto, IVA y total de un conjunto de líneas.
     *
     * @param  iterable<array{0: string, 1: string}>  $lineas  [precio, cantidad]
     * @return array{neto: int, iva: int, total: int}
     */
    public static function totales(iterable $lineas): array
    {
        $neto = 0;

        foreach ($lineas as [$precio, $cantidad]) {
            $neto += self::subtotalNeto($precio, $cantidad);
        }

        $iva = self::iva($neto);

        return ['neto' => $neto, 'iva' => $iva, 'total' => $neto + $iva];
    }

    public static function iva(int $neto): int
    {
        return intdiv($neto * config('negocio.iva_pct') * 2 + 100, 200);
    }

    public static function centesimas(string $valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    public static function decimal(int $centesimas): string
    {
        return number_format($centesimas / 100, 2, '.', '');
    }

    /** 2,5 · 10 · 1.250 (sin ceros decimales sobrantes). */
    public static function legible(int $centesimas): string
    {
        return rtrim(rtrim(number_format($centesimas / 100, 2, ',', '.'), '0'), ',');
    }
}
