<?php

namespace Database\Seeders;

use App\Models\LoginAttempt;
use Illuminate\Database\Seeder;

/**
 * Datos SIMULADOS para practicar el análisis (sección 9 del TP).
 * Usa IPs de documentación (RFC 5737), que no pertenecen a nadie.
 *
 *   php artisan db:seed --class=SecurityDemoSeeder
 */
class SecurityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $hash = fn (string $email) => LoginAttempt::hashIdentifier($email);
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0';
        $rows = [];

        // 203.0.113.50: ráfaga de 8 fallos en ~2 minutos contra 4 cuentas distintas (patrón de ataque)
        foreach (range(1, 8) as $i) {
            $rows[] = ['203.0.113.50', 'failed', $hash(['admin@monitor.test', 'root@monitor.test', 'test@monitor.test', 'soporte@monitor.test'][$i % 4]), now()->subSeconds(130 - $i * 15), 'python-requests/2.32'];
        }
        // ...y luego intentos rechazados por el bloqueo
        foreach (range(1, 3) as $i) {
            $rows[] = ['203.0.113.50', 'blocked', $hash('admin@monitor.test'), now()->subSeconds(10 - $i * 3), 'python-requests/2.32'];
        }

        // 198.51.100.23: 3 fallos y después éxito, misma cuenta (usuario que olvidó la clave)
        foreach (range(1, 3) as $i) {
            $rows[] = ['198.51.100.23', 'failed', $hash('usuario@monitor.test'), now()->subMinutes(4)->addSeconds($i * 20), $ua];
        }
        $rows[] = ['198.51.100.23', 'success', $hash('usuario@monitor.test'), now()->subMinutes(3), $ua];

        // 192.0.2.10: IP compartida (laboratorio / NAT): varios usuarios legítimos, algún fallo aislado
        foreach (['a', 'b', 'c', 'd'] as $i => $u) {
            $rows[] = ['192.0.2.10', 'success', $hash("alumno{$u}@monitor.test"), now()->subMinutes(30 - $i * 5), $ua];
        }
        $rows[] = ['192.0.2.10', 'failed', $hash('alumnoc@monitor.test'), now()->subMinutes(21), $ua];

        foreach ($rows as [$ip, $status, $identifier, $at, $agent]) {
            LoginAttempt::create([
                'identifier_hash' => $identifier,
                'ip_address' => $ip,
                'status' => $status,
                'user_agent' => $agent,
                'created_at' => $at,
            ]);
        }
    }
}
