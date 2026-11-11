<?php

/*
|--------------------------------------------------------------------------
| Parámetros de negocio (docs/BUSINESS_RULES.md)
|--------------------------------------------------------------------------
|
| Montos y plazos marcados como «supuesto» en las reglas. Viven aquí y en el
| .env, nunca como literales en el código (AGENTS.md).
|
*/

return [

    // RN-03: pedido mínimo en CLP. B2B se mide en neto.
    'pedido_minimo' => [
        'b2c' => (int) env('PEDIDO_MINIMO_B2C', 20000),
        'b2b' => (int) env('PEDIDO_MINIMO_B2B', 150000),
    ],

    // RN-04: minutos que un pedido B2C reserva stock esperando el pago.
    'reserva_stock_minutos_b2c' => (int) env('RESERVA_STOCK_MINUTOS_B2C', 30),

    // RN-04: horas sin actividad para marcar un carrito como abandonado.
    'carrito_abandono_horas' => (int) env('CARRITO_ABANDONO_HORAS', 24),

    // Panel del CRM (BUSINESS_RULES.md §3, «otros supuestos configurables»):
    // días sin interacciones para marcar una oportunidad como «sin actividad»
    // y ventana en días de la tasa de conversión y de los pedidos por canal.
    'oportunidad_sin_actividad_dias' => (int) env('OPORTUNIDAD_SIN_ACTIVIDAD_DIAS', 15),
    'conversion_ventana_dias' => (int) env('CONVERSION_VENTANA_DIAS', 90),

    // IVA en porcentaje.
    'iva_pct' => (int) env('IVA_PCT', 19),

];
