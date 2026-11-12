<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\Crm;
use App\Http\Controllers\Crm\Admin;
use App\Http\Controllers\Portal;
use Illuminate\Support\Facades\Route;

// Portal de ventas (clientes B2C y B2B).
Route::get('/', Portal\InicioController::class)->name('portal.inicio');
Route::get('/catalogo', [Portal\CatalogoController::class, 'index'])->name('portal.catalogo');
Route::get('/productos/{sku}', [Portal\CatalogoController::class, 'show'])->name('portal.productos.show');

// Carrito (T-010): solo cuentas de cliente; cada una ve únicamente el suyo.
Route::prefix('carrito')->middleware(['auth', 'can:portal.comprar'])->group(function () {
    Route::get('/', [Portal\CarritoController::class, 'show'])->name('portal.carrito');
    Route::post('/items', [Portal\CarritoController::class, 'store'])->name('portal.carrito.items.store');
    Route::patch('/items/{sku}', [Portal\CarritoController::class, 'update'])->name('portal.carrito.items.update');
    Route::delete('/items/{sku}', [Portal\CarritoController::class, 'destroy'])->name('portal.carrito.items.destroy');
});

// Checkout, pedido y pago simulado (T-011).
Route::middleware(['auth', 'can:portal.comprar'])->group(function () {
    Route::get('/checkout', [Portal\CheckoutController::class, 'show'])->name('portal.checkout');
    Route::post('/pedidos', [Portal\CheckoutController::class, 'store'])->name('portal.pedidos.store');
    Route::get('/pedidos/{pedido}/pago', [Portal\PagoController::class, 'show'])->whereNumber('pedido')->name('portal.pedidos.pago');
    Route::post('/pedidos/{pedido}/pago', [Portal\PagoController::class, 'store'])->whereNumber('pedido')->name('portal.pedidos.pago.store');
});

// Seguimiento de pedidos y puntos de entrega (T-012). Un pedido o punto ajeno: 404.
Route::middleware(['auth', 'can:portal.comprar'])->group(function () {
    Route::get('/mis-pedidos', [Portal\MisPedidosController::class, 'index'])->name('portal.mis-pedidos');
    Route::get('/mis-pedidos/{pedido}', [Portal\MisPedidosController::class, 'show'])->whereNumber('pedido')->name('portal.mis-pedidos.show');
    Route::post('/mis-pedidos/{pedido}/cancelar', [Portal\MisPedidosController::class, 'cancelar'])->whereNumber('pedido')->name('portal.mis-pedidos.cancelar');
    Route::post('/mis-pedidos/{pedido}/repetir', [Portal\MisPedidosController::class, 'repetir'])->whereNumber('pedido')->name('portal.mis-pedidos.repetir');

    Route::get('/mis-direcciones', [Portal\DireccionController::class, 'index'])->name('portal.direcciones');
    Route::post('/mis-direcciones', [Portal\DireccionController::class, 'store'])->name('portal.direcciones.store');
    Route::patch('/mis-direcciones/{direccion}', [Portal\DireccionController::class, 'update'])->whereNumber('direccion')->name('portal.direcciones.update');

    // Tickets (T-018): «Reportar un problema», opcionalmente sobre un pedido propio.
    Route::get('/tickets', [Portal\TicketController::class, 'index'])->name('portal.tickets');
    Route::post('/tickets', [Portal\TicketController::class, 'store'])->middleware('throttle:10,1')->name('portal.tickets.store');

    // Asistente IA (T-021): JSON; el servidor arma el contexto y llama a la IA.
    Route::get('/asistente', [Portal\AsistenteController::class, 'show'])->name('portal.asistente');
    Route::post('/asistente/mensajes', [Portal\AsistenteController::class, 'store'])->middleware('throttle:asistente')->name('portal.asistente.mensajes');
});

// Sesión (clientes y personal).
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'mostrar'])->name('login');
    Route::post('/login', [LoginController::class, 'iniciar'])->middleware('throttle:login')->name('login.iniciar');

    // Registro de clientes (T-009): persona activa de inmediato; empresa pendiente de validación (RN-02).
    Route::get('/registro/persona', [RegistroController::class, 'persona'])->name('registro.persona');
    Route::post('/registro/persona', [RegistroController::class, 'registrarPersona'])->middleware('throttle:10,1')->name('registro.persona.store');
    Route::get('/registro/empresa', [RegistroController::class, 'empresa'])->name('registro.empresa');
    Route::post('/registro/empresa', [RegistroController::class, 'registrarEmpresa'])->middleware('throttle:10,1')->name('registro.empresa.store');
});
Route::post('/logout', [LoginController::class, 'cerrar'])->middleware('auth')->name('logout');

// CRM (vendedor, despachador, administrador). Un cliente recibe 403.
Route::prefix('crm')->name('crm.')->middleware(['auth', 'rol:administrador,vendedor,despachador'])->group(function () {
    Route::get('/', Crm\InicioController::class)->name('inicio');

    Route::middleware('can:clientes')->group(function () {
        Route::get('/clientes', [Crm\ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/nuevo', [Crm\ClienteController::class, 'create'])->name('clientes.create');
        Route::post('/clientes/nuevo', [Crm\ClienteController::class, 'store'])->name('clientes.store');
        Route::get('/clientes/{cliente}', [Crm\ClienteController::class, 'show'])->whereNumber('cliente')->name('clientes.show');
        Route::post('/clientes/{cliente}/validar', [Crm\ClienteController::class, 'validar'])->whereNumber('cliente')->name('clientes.validar');
        Route::post('/clientes/{cliente}/contactos', [Crm\FichaClienteController::class, 'contacto'])->whereNumber('cliente')->name('clientes.contactos.store');
        Route::post('/clientes/{cliente}/interacciones', [Crm\FichaClienteController::class, 'interaccion'])->whereNumber('cliente')->name('clientes.interacciones.store');
        Route::get('/clientes/{cliente}/pedidos/nuevo', [Crm\PedidoTelefonicoController::class, 'create'])->whereNumber('cliente')->name('clientes.pedidos.create');
        Route::post('/clientes/{cliente}/pedidos', [Crm\PedidoTelefonicoController::class, 'store'])->whereNumber('cliente')->name('clientes.pedidos.store');
    });

    // Pedidos (T-016): vendedor (su cartera), despachador y administrador.
    Route::middleware('can:pedidos')->group(function () {
        Route::get('/pedidos', [Crm\PedidoController::class, 'index'])->name('pedidos');
        Route::get('/pedidos/{pedido}', [Crm\PedidoController::class, 'show'])->whereNumber('pedido')->name('pedidos.show');
        Route::post('/pedidos/{pedido}/estado', [Crm\PedidoController::class, 'estado'])->whereNumber('pedido')->name('pedidos.estado');
        Route::post('/pedidos/{pedido}/confirmar-oc', [Crm\PedidoController::class, 'confirmarOc'])->whereNumber('pedido')->name('pedidos.confirmar-oc');
    });

    // Logística (T-017): despachos y flota para despachador y administrador;
    // alertas también para el vendedor (las de su cartera).
    Route::middleware('can:despachos')->group(function () {
        Route::get('/despachos', [Crm\DespachoController::class, 'index'])->name('despachos');
        Route::post('/despachos', [Crm\DespachoController::class, 'store'])->name('despachos.store');
        Route::get('/despachos/{despacho}', [Crm\DespachoController::class, 'show'])->whereNumber('despacho')->name('despachos.show');
        Route::post('/despachos/{despacho}/salida', [Crm\DespachoController::class, 'salida'])->whereNumber('despacho')->name('despachos.salida');
        Route::post('/despachos/{despacho}/paradas/{pedido}', [Crm\DespachoController::class, 'parada'])->whereNumber(['despacho', 'pedido'])->name('despachos.parada');
    });
    Route::get('/flota', Crm\FlotaController::class)->middleware('can:flota')->name('flota');
    Route::middleware('can:alertas')->group(function () {
        Route::get('/alertas', [Crm\AlertaController::class, 'index'])->name('alertas');
        Route::post('/alertas/{alerta}/resolver', [Crm\AlertaController::class, 'resolver'])->whereNumber('alerta')->name('alertas.resolver');
    });

    // Tickets (T-018): vendedor (su cartera) y administrador.
    Route::middleware('can:tickets')->group(function () {
        Route::get('/tickets', [Crm\TicketController::class, 'index'])->name('tickets');
        Route::get('/tickets/{ticket}', [Crm\TicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
        Route::patch('/tickets/{ticket}', [Crm\TicketController::class, 'update'])->whereNumber('ticket')->name('tickets.update');
    });

    // Secciones del menú que se construyen en tareas posteriores: ya tienen su
    // ruta y su control de acceso, para que el menú por rol sea real.
    foreach ([
        'oportunidades' => ['oportunidades', 'Pipeline de oportunidades', 'T-015'],
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
