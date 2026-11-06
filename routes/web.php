<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\SecurityDashboardController;
use App\Http\Middleware\SecurityLoginGuard;
use App\Http\Controllers\ServerController;
use Illuminate\Support\Facades\Route;

// La raíz abre directamente el login (antes mostraba la bienvenida de Laravel).
// Si ya hay sesión iniciada, va al monitor.
Route::get('/', function () {
    return auth()->check() ? redirect('/servidor') : redirect()->route('login');
});

Route::get('/servidor', [ServerController::class, 'index']);

Route::post('/demo/abrir/{app}', [DemoController::class, 'abrir'])
    ->whereIn('app', ['calculadora', 'bloc', 'paint'])
    ->middleware('throttle:2,1'); // cada PC puede abrir 2 programas por minuto

Route::get('/graficos', fn () => view('graficos'));

// ---------------------------------------------------------------------------
// TP 3 · Logging y detección de incidentes
// ---------------------------------------------------------------------------

// Login (guard "web" de sesión). El middleware SecurityLoginGuard se aplica
// SOLO a la ruta POST, antes de que el controlador ejecute Auth::attempt().
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware(SecurityLoginGuard::class)
        ->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Panel de seguridad: requiere sesión y el permiso viewSecurityDashboard (solo admins).
Route::middleware(['auth', 'can:viewSecurityDashboard'])->group(function () {
    Route::get('/seguridad', [SecurityDashboardController::class, 'index'])->name('seguridad.panel');
    Route::get('/seguridad/exportar', [SecurityDashboardController::class, 'export'])->name('seguridad.export');
});
