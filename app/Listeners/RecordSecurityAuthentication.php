<?php

namespace App\Listeners;

use App\Models\LoginAttempt;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

/**
 * Escucha los eventos estándar de Laravel (Login y Failed) que dispara Auth::attempt().
 * No reemplaza el login: solo observa lo que ya pasa.
 *
 * Se registra solo por "event discovery" (Laravel 11+ escanea app/Listeners y lee el
 * type-hint Login|Failed). Por eso NO se agrega Event::listen() en AppServiceProvider:
 * se registraría dos veces. Verificar con: php artisan event:list
 */
class RecordSecurityAuthentication
{
    public function handle(Login|Failed $event): void
    {
        $request = request();
        $status = $event instanceof Login ? 'success' : 'failed';

        $identifier = $event instanceof Login
            ? (string) $event->user->email
            : (string) ($event->credentials['email'] ?? '');

        LoginAttempt::create([
            'identifier_hash' => LoginAttempt::hashIdentifier($identifier),
            'ip_address' => $request->ip(),
            'status' => $status,
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'created_at' => now(),
        ]);

        // success -> INFO (nivel 6)   |   failed -> WARNING (nivel 4)
        Log::channel('security')->log(
            $status === 'success' ? 'info' : 'warning',
            'Authentication '.$status,
            ['ip' => $request->ip(), 'guard' => $event->guard]
        );
    }
}
