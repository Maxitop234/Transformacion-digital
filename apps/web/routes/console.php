<?php

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

// Hay dos instancias web: el lock en la caché de la base deja correr una sola.
Schedule::command('carritos:marcar-abandonados')->everyFifteenMinutes()->onOneServer()->withoutOverlapping();
