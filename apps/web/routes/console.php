<?php

use App\Domain\Logistica\NotificarAlertasNuevas;
use App\Domain\Ventas\CancelarPedidosSinPago;
use App\Domain\Ventas\MarcarCarritosAbandonados;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RN-04: carritos sin actividad por `negocio.carrito_abandono_horas` → abandonado (T-010).
Artisan::command('carritos:marcar-abandonados', function (MarcarCarritosAbandonados $marcar) {
    $this->info('Carritos marcados como abandonados: '.$marcar());
})->purpose('Marca como abandonados los carritos sin actividad');

// RN-04: pedidos B2C sin pago tras `negocio.reserva_stock_minutos_b2c` → Cancelado (T-011).
Artisan::command('pedidos:cancelar-sin-pago', function (CancelarPedidosSinPago $cancelar) {
    $this->info('Pedidos B2C cancelados por falta de pago: '.$cancelar());
})->purpose('Cancela los pedidos B2C sin pago y libera su reserva');

// T-017: aviso de rupturas de frío nuevas al despachador y a los vendedores afectados.
Artisan::command('alertas:notificar', function (NotificarAlertasNuevas $notificar) {
    $this->info('Alertas de frío avisadas: '.$notificar());
})->purpose('Avisa las alertas de frío nuevas al despachador y a los vendedores afectados');

// Hay dos instancias web: el lock en la caché de la base deja correr una sola.
Schedule::command('carritos:marcar-abandonados')->everyFifteenMinutes()->onOneServer()->withoutOverlapping();
Schedule::command('pedidos:cancelar-sin-pago')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('alertas:notificar')->everyMinute()->onOneServer()->withoutOverlapping();
