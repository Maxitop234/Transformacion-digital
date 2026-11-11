<?php

namespace Database\Seeders\Demo;

use App\Domain\Clientes\Rut;

/**
 * Datos personales ficticios para la demo (AGENTS.md: nunca datos reales).
 *
 * - RUT: se generan en el rango 45.000.000–45.999.999, que el proyecto reserva
 *   para pruebas; todos llevan dígito verificador válido.
 * - Correos en dominios `.example` (RFC 2606), que no pueden existir.
 * - Teléfonos con el bloque 5555, que no se asigna a líneas reales en la demo.
 */
final class Ficticio
{
    public const RUT_MIN = 45_000_000;

    public const RUT_MAX = 45_999_999;

    public const NOMBRES = ['Camila', 'Matías', 'Valentina', 'Benjamín', 'Antonia', 'Vicente', 'Josefa', 'Martín', 'Isidora', 'Agustín', 'Florencia', 'Tomás', 'Fernanda', 'Joaquín', 'Catalina', 'Diego', 'Javiera', 'Felipe', 'Constanza', 'Sebastián'];

    public const APELLIDOS = ['Rojas', 'Muñoz', 'Soto', 'Contreras', 'Silva', 'Martínez', 'Sepúlveda', 'Morales', 'Rodríguez', 'López', 'Fuentes', 'Hernández', 'Torres', 'Araya', 'Flores', 'Espinoza', 'Valenzuela', 'Castillo', 'Tapia', 'Reyes'];

    public static function rut(int $desplazamiento): string
    {
        $numero = self::RUT_MIN + $desplazamiento;

        if ($numero > self::RUT_MAX) {
            throw new \OverflowException('Se agotó el rango de RUT ficticios.');
        }

        return Rut::desdeNumero($numero)->normalizado();
    }

    public static function nombre(Azar $azar): string
    {
        return $azar->uno(self::NOMBRES).' '.$azar->uno(self::APELLIDOS).' '.$azar->uno(self::APELLIDOS);
    }

    public static function telefono(int $n): string
    {
        return sprintf('+56 9 5555 %04d', $n);
    }

    public static function correo(string $nombre, string $dominio = 'correo.example'): string
    {
        $base = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $nombre));
        $partes = explode(' ', preg_replace('/[^a-z ]/', '', $base));

        return $partes[0].'.'.($partes[1] ?? 'demo').'@'.$dominio;
    }
}
