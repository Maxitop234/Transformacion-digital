<?php

namespace App\Domain\Clientes;

use App\Models\Rol;

/**
 * Matriz de acceso por rol a cada área de la aplicación
 * (BUSINESS_RULES.md §1, API.md §1.4). Cada área es un gate:
 * `@can('oportunidades')`, `->middleware('can:oportunidades')`.
 */
final class Permisos
{
    public const AREAS = [
        // Portal
        'portal.comprar' => [Rol::CLIENTE_B2B, Rol::CLIENTE_B2C],
        // CRM
        'crm' => Rol::PERSONAL,
        'clientes' => [Rol::ADMINISTRADOR, Rol::VENDEDOR],
        'oportunidades' => [Rol::ADMINISTRADOR, Rol::VENDEDOR],
        'pedidos' => [Rol::ADMINISTRADOR, Rol::VENDEDOR, Rol::DESPACHADOR],
        'despachos' => [Rol::ADMINISTRADOR, Rol::DESPACHADOR],
        'flota' => [Rol::ADMINISTRADOR, Rol::DESPACHADOR],
        'alertas' => [Rol::ADMINISTRADOR, Rol::VENDEDOR, Rol::DESPACHADOR],
        'tickets' => [Rol::ADMINISTRADOR, Rol::VENDEDOR],
        'administracion' => [Rol::ADMINISTRADOR],
    ];
}
