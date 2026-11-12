{{--
    Insignia de estado con color por significado (T-028): el mismo estado se
    ve igual en el portal y en el CRM. `valor` es el valor del dominio
    (estado de pedido, ticket, entrega, cliente, etapa, prioridad); la
    etiqueta se deriva de él salvo que se pase otra. El color nunca es la
    única señal: siempre va el texto.
--}}
@props(['valor', 'etiqueta' => null])
@php
    $tono = match ($valor) {
        'Entregado', 'Ganado', 'activo', 'resuelto', 'aprobado', 'entregado', 'finalizado', 'resuelta', 'convertido' => 'exito',
        'Confirmado', 'Contactado', 'Calificado', 'programado', 'baja' => 'info',
        'En preparación', 'En ruta', 'Propuesta', 'Negociación', 'en_proceso', 'en_ruta' => 'progreso',
        'Pendiente', 'Prospecto nuevo', 'pendiente', 'pendiente_validacion', 'prospecto', 'abierto', 'media', 'fallido', 'sin_clasificar' => 'aviso',
        'Cancelado', 'Rechazado', 'Perdido', 'rechazado', 'rechazado_temperatura', 'alta', 'abierta', 'cancelado' => 'peligro',
        default => 'neutro',
    };
    $vivo = in_array($valor, ['En ruta', 'en_ruta', 'en_proceso'], true);
@endphp
<span {{ $attributes->class(['insignia'])->merge(['data-tono' => $tono, 'data-vivo' => $vivo ? '' : null]) }}>{{ $etiqueta ?? str_replace('_', ' ', $valor) }}</span>
