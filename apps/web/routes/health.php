<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Sin el grupo `web`: no abre sesión ni toca la base más que con `select 1`,
// así un fallo de la base devuelve 503 y no un error de sesión.
Route::get('/health', HealthController::class)->name('health');
