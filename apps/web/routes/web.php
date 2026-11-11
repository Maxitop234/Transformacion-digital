<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Crm;
use App\Http\Controllers\Portal;
use Illuminate\Support\Facades\Route;

// Portal de ventas (clientes B2C y B2B).
Route::get('/', Portal\InicioController::class)->name('portal.inicio');

// Sesión (clientes y personal).
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'mostrar'])->name('login');
    Route::post('/login', [LoginController::class, 'iniciar'])->middleware('throttle:login')->name('login.iniciar');
});
Route::post('/logout', [LoginController::class, 'cerrar'])->middleware('auth')->name('logout');

// CRM (vendedor, despachador, administrador). Un cliente recibe 403.
Route::prefix('crm')->name('crm.')->middleware(['auth', 'rol:administrador,vendedor,despachador'])->group(function () {
    Route::get('/', Crm\InicioController::class)->name('inicio');

    Route::middleware('can:clientes')->group(function () {
        Route::get('/clientes', [Crm\ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/{cliente}', [Crm\ClienteController::class, 'show'])->whereNumber('cliente')->name('clientes.show');
    });

    // Secciones del menú que se construyen en tareas posteriores: ya tienen su
    // ruta y su control de acceso, para que el menú por rol sea real.
    foreach ([
        'oportunidades' => ['oportunidades', 'Pipeline de oportunidades', 'T-015'],
        'pedidos' => ['pedidos', 'Pedidos', 'T-016'],
        'despachos' => ['despachos', 'Despachos', 'T-017'],
        'flota' => ['flota', 'Flota', 'T-017'],
        'alertas' => ['alertas', 'Alertas de frío', 'T-017'],
        'tickets' => ['tickets', 'Tickets', 'T-018'],
        'admin' => ['administracion', 'Administración', 'T-007'],
    ] as $ruta => [$area, $titulo, $tarea]) {
        Route::view("/{$ruta}", 'crm.seccion-pendiente', ['titulo' => $titulo, 'tarea' => $tarea])
            ->middleware("can:{$area}")
            ->name($ruta);
    }
});
