<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Crm;
use App\Http\Controllers\Crm\Admin;
use App\Http\Controllers\Portal;
use Illuminate\Support\Facades\Route;

// Portal de ventas (clientes B2C y B2B).
Route::get('/', Portal\InicioController::class)->name('portal.inicio');
Route::get('/catalogo', [Portal\CatalogoController::class, 'index'])->name('portal.catalogo');
Route::get('/productos/{sku}', [Portal\CatalogoController::class, 'show'])->name('portal.productos.show');

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
    ] as $ruta => [$area, $titulo, $tarea]) {
        Route::view("/{$ruta}", 'crm.seccion-pendiente', ['titulo' => $titulo, 'tarea' => $tarea])
            ->middleware("can:{$area}")
            ->name($ruta);
    }

    // Mantenedores de administración (T-007): solo el administrador.
    Route::prefix('admin')->middleware('can:administracion')->group(function () {
        Route::view('/', 'crm.admin.inicio')->name('admin');

        Route::name('admin.')->group(function () {
            Route::get('/productos', [Admin\ProductoController::class, 'index'])->name('productos.index');
            Route::get('/productos/nuevo', [Admin\ProductoController::class, 'create'])->name('productos.create');
            Route::post('/productos', [Admin\ProductoController::class, 'store'])->name('productos.store');
            Route::get('/productos/{producto}', [Admin\ProductoController::class, 'edit'])->whereNumber('producto')->name('productos.edit');
            Route::patch('/productos/{producto}', [Admin\ProductoController::class, 'update'])->whereNumber('producto')->name('productos.update');

            Route::get('/listas-precio', [Admin\ListaPrecioController::class, 'index'])->name('listas-precio.index');
            Route::get('/listas-precio/nueva', [Admin\ListaPrecioController::class, 'create'])->name('listas-precio.create');
            Route::post('/listas-precio', [Admin\ListaPrecioController::class, 'store'])->name('listas-precio.store');
            Route::get('/listas-precio/{lista}', [Admin\ListaPrecioController::class, 'edit'])->whereNumber('lista')->name('listas-precio.edit');
            Route::patch('/listas-precio/{lista}', [Admin\ListaPrecioController::class, 'update'])->whereNumber('lista')->name('listas-precio.update');
            Route::patch('/listas-precio/{lista}/precios', [Admin\ListaPrecioController::class, 'precios'])->whereNumber('lista')->name('listas-precio.precios');

            Route::get('/inventario', [Admin\InventarioController::class, 'index'])->name('inventario.index');
            Route::post('/inventario', [Admin\InventarioController::class, 'store'])->name('inventario.store');

            Route::get('/usuarios', [Admin\UsuarioController::class, 'index'])->name('usuarios.index');
            Route::get('/usuarios/nuevo', [Admin\UsuarioController::class, 'create'])->name('usuarios.create');
            Route::post('/usuarios', [Admin\UsuarioController::class, 'store'])->name('usuarios.store');
            Route::get('/usuarios/{usuario}', [Admin\UsuarioController::class, 'edit'])->whereNumber('usuario')->name('usuarios.edit');
            Route::patch('/usuarios/{usuario}', [Admin\UsuarioController::class, 'update'])->whereNumber('usuario')->name('usuarios.update');
            Route::patch('/usuarios/{usuario}/cartera', [Admin\UsuarioController::class, 'reasignarCartera'])->whereNumber('usuario')->name('usuarios.cartera');
        });
    });
});
