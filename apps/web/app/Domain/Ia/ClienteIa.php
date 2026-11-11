<?php

namespace App\Domain\Ia;

/**
 * Puerto hacia la API de IA externa (ADR-0015). El resto de la aplicación
 * depende de esta interfaz, no del proveedor: `ClienteIaCompatibleOpenAi` en
 * producción y `ClienteIaFalso` en los tests.
 *
 * Quien llama ya depuró los datos personales (`FiltroDatosPersonales`) y
 * valida la respuesta contra sus listas: el modelo no es de confianza.
 */
interface ClienteIa
{
    /**
     * Pide una respuesta JSON que cumpla `$esquema` (JSON Schema).
     *
     * @param  string  $sistema  instrucciones fijas del sistema
     * @param  string  $usuario  datos ya depurados, delimitados como dato
     * @param  array<string, mixed>  $esquema
     * @return array<string, mixed>
     *
     * @throws IaNoDisponible si no hay clave, la API falla, se agota el tiempo o la respuesta no es JSON
     */
    public function completarJson(string $sistema, string $usuario, string $nombreEsquema, array $esquema): array;
}
