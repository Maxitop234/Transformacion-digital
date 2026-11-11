<?php

namespace App\Notifications;

use App\Models\Alerta;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Aviso de una ruptura de frío nueva (T-017) para el despachador y los
 * vendedores de los clientes afectados. Es un correo interno del equipo: no
 * va a clientes ni incluye datos del conductor.
 */
class AlertaFrioNueva extends Notification
{
    /**
     * @param  Collection<int, object>  $afectados  filas de AfectadosPorAlerta
     */
    public function __construct(public readonly Alerta $alerta, public readonly Collection $afectados) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lectura = $this->alerta->telemetria;
        $mensaje = (new MailMessage)
            ->subject("Ruptura de frío en {$lectura->camion_id} (alerta {$this->alerta->id_alerta})")
            ->line(sprintf(
                'El camión %s registró %s °C el %s (severidad %s).',
                $lectura->camion_id,
                number_format((float) $lectura->temp_camara, 1, ',', '.'),
                $lectura->fecha_hora->setTimezone(config('app.timezone'))->format('d-m-Y H:i'),
                $this->alerta->severidad,
            ));

        if ($this->afectados->isEmpty()) {
            $mensaje->line('El camión no lleva pedidos en ruta.');
        } else {
            $mensaje->line('Pedidos afectados:');
            foreach ($this->afectados as $fila) {
                $mensaje->line("- Parada {$fila->orden_parada}: pedido N.º {$fila->id_pedido} de {$fila->nombre_razon_social}");
            }
        }

        return $mensaje->action('Ver alertas', route('crm.alertas'));
    }
}
