<?php

use App\Http\Controllers\Crm;
use App\Http\Controllers\Portal;
use Illuminate\Support\Facades\Route;

// Portal de ventas (clientes B2C y B2B).
Route::get('/', Portal\InicioController::class)->name('portal.inicio');

// CRM (vendedor, despachador, administrador). La autenticación y el control
// por rol se agregan en T-006.
Route::prefix('crm')->name('crm.')->group(function () {
    Route::get('/', Crm\InicioController::class)->name('inicio');
});
