<?php

namespace App\Domain\Ia;

/**
 * Enmascara datos personales del texto libre antes de enviarlo a la IA
 * (RN-11, SECURITY.md §9): correos, RUT, teléfonos y los nombres conocidos
 * del cliente (razón social, contactos) que se le pasen.
 *
 * Es una red de seguridad: los prompts ya se arman solo con identificadores
 * internos y estados, nunca con columnas personales.
 */
final class FiltroDatosPersonales
{
    private const PATRONES = [
        // Correos.
        '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu' => '[correo]',
        // RUT con o sin puntos, con guion o con dígito verificador pegado (7-8 dígitos + DV).
        '/\b\d{1,2}(?:\.\d{3}){2}\s*-\s*[\dkK]\b/u' => '[rut]',
        '/\b\d{7,8}\s*-\s*[\dkK]\b/u' => '[rut]',
        '/\b\d{7,8}[kK]\b/u' => '[rut]',
        // Teléfonos chilenos: +56 9 1234 5678, 56912345678, 9 1234 5678, (45) 221 2345.
        '/(?:\+?56[\s.\-]?)?(?:\(?\d{1,2}\)?[\s.\-]?)?\d{4}[\s.\-]?\d{4}\b/u' => '[telefono]',
        '/(?:\+?56[\s.\-]?)?\(?\d{2}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}\b/u' => '[telefono]',
    ];

    /**
     * @param  iterable<string|null>  $nombres  nombres propios a ocultar (cliente, contactos)
     */
    public function enmascarar(string $texto, iterable $nombres = []): string
    {
        foreach ($nombres as $nombre) {
            $nombre = trim((string) $nombre);

            if (mb_strlen($nombre) >= 3) {
                $texto = preg_replace('/'.preg_quote($nombre, '/').'/iu', '[nombre]', $texto) ?? $texto;
            }
        }

        foreach (self::PATRONES as $patron => $marca) {
            $texto = preg_replace($patron, $marca, $texto) ?? $texto;
        }

        return $texto;
    }
}
