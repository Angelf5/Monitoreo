<?php

namespace App\Http\Middleware;

use App\Models\LoginAttempt;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se aplica SOLO a POST /login. Evalúa el historial ANTES de dejar pasar el intento.
 *
 * Reglas por IP (ventana de 5 minutos):
 *   1–2 fallos -> WARNING (lo escribe el listener al registrar cada fallo)
 *   3–4 fallos -> ALERT "Possible brute force"
 *   5+ fallos  -> bloqueo 10 minutos + CRITICAL + HTTP 429
 *   IP ya bloqueada -> HTTP 429 + registro "blocked"
 *
 * Mejora (actividad integradora): detección por cuenta. Si un mismo email acumula
 * 5 fallos en 15 minutos, aunque vengan de IPs distintas, se bloquea esa cuenta
 * 10 minutos (ataque distribuido / password spraying contra un usuario).
 */
class SecurityLoginGuard
{
    public const IP_WINDOW_MINUTES = 5;
    public const IP_ALERT_AT = 3;
    public const IP_BLOCK_AT = 5;
    public const BLOCK_MINUTES = 10;

    public const ACCOUNT_WINDOW_MINUTES = 15;
    public const ACCOUNT_BLOCK_AT = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $ipKey = 'security:block:'.hash('sha256', $ip);

        $identifierHash = LoginAttempt::hashIdentifier($request->input('email'));
        $accountKey = $identifierHash ? 'security:block-account:'.$identifierHash : null;

        // 1) ¿La IP o la cuenta ya están bloqueadas?
        $ipBlocked = Cache::has($ipKey);
        $accountBlocked = $accountKey && Cache::has($accountKey);

        if ($ipBlocked || $accountBlocked) {
            $this->recordBlocked($request, $identifierHash);
            Log::channel('security')->warning('Blocked login attempt', [
                'ip' => $ip,
                'reason' => $ipBlocked ? 'ip' : 'account',
            ]);

            abort(429, 'Acceso temporalmente restringido');
        }

        // 2) Fallos recientes desde esta IP
        $failures = LoginAttempt::where('ip_address', $ip)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes(self::IP_WINDOW_MINUTES))
            ->count();

        if ($failures >= self::IP_BLOCK_AT) {
            Cache::put($ipKey, true, now()->addMinutes(self::BLOCK_MINUTES));
            Log::channel('security')->critical('Temporary IP block', [
                'ip' => $ip,
                'failures' => $failures,
                'duration_minutes' => self::BLOCK_MINUTES,
            ]);

            abort(429, 'Acceso temporalmente restringido');
        }

        // 3) Mejora: fallos recientes contra esta cuenta (desde cualquier IP)
        if ($identifierHash) {
            $accountFailures = LoginAttempt::where('identifier_hash', $identifierHash)
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subMinutes(self::ACCOUNT_WINDOW_MINUTES))
                ->count();

            if ($accountFailures >= self::ACCOUNT_BLOCK_AT) {
                Cache::put($accountKey, true, now()->addMinutes(self::BLOCK_MINUTES));
                Log::channel('security')->critical('Temporary account block', [
                    'ip' => $ip,
                    'account' => substr($identifierHash, 0, 12),
                    'failures' => $accountFailures,
                    'duration_minutes' => self::BLOCK_MINUTES,
                ]);

                abort(429, 'Acceso temporalmente restringido');
            }
        }

        // 4) Detección sin bloqueo: 3–4 fallos -> ALERT
        if ($failures >= self::IP_ALERT_AT) {
            Log::channel('security')->alert('Possible brute force', [
                'ip' => $ip,
                'failures' => $failures,
            ]);
        }

        return $next($request);
    }

    private function recordBlocked(Request $request, ?string $identifierHash): void
    {
        LoginAttempt::create([
            'identifier_hash' => $identifierHash,
            'ip_address' => $request->ip(),
            'status' => 'blocked',
            'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            'created_at' => now(),
        ]);
    }
}
