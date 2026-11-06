<?php

namespace Tests\Feature;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Tests\TestCase;

/**
 * Pruebas obligatorias del TP 3 (sección 8) + las dos mejoras.
 * Ejecutar con: php artisan test --filter=SecurityLoggingTest
 */
class SecurityLoggingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // En los tests el canal "security" escribe en memoria para poder inspeccionarlo.
        config(['logging.channels.security' => [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
            'level' => 'debug',
        ]]);

        $this->user = User::create([
            'name' => 'Prueba',
            'email' => 'prueba@monitor.test',
            'password' => 'Correcta123!',
        ]);
    }

    private function securityLog(): TestHandler
    {
        return Log::channel('security')->getLogger()->getHandlers()[0];
    }

    private function countLevel(Level $level): int
    {
        return count(array_filter(
            $this->securityLog()->getRecords(),
            fn ($r) => $r->level === $level
        ));
    }

    private function fail(string $email = 'prueba@monitor.test')
    {
        return $this->post('/login', ['email' => $email, 'password' => 'incorrecta']);
    }

    public function test_login_valido_registra_info_y_success(): void
    {
        $this->post('/login', ['email' => 'prueba@monitor.test', 'password' => 'Correcta123!'])
            ->assertRedirect('/servidor');

        $this->assertAuthenticatedAs($this->user);
        $this->assertDatabaseHas('login_attempts', ['status' => 'success', 'ip_address' => '127.0.0.1']);
        $this->assertTrue($this->securityLog()->hasInfoThatContains('Authentication success'));
    }

    public function test_dos_contrasenas_incorrectas_generan_dos_warning_y_dos_failed(): void
    {
        $this->fail()->assertSessionHasErrors('email');
        $this->fail()->assertSessionHasErrors('email');

        $this->assertSame(2, LoginAttempt::where('status', 'failed')->count());
        $this->assertSame(2, $this->countLevel(Level::Warning));
        $this->assertSame(0, $this->countLevel(Level::Alert));
    }

    public function test_tres_fallos_generan_alert_en_el_intento_siguiente(): void
    {
        $this->fail(); $this->fail(); $this->fail();
        $this->assertSame(0, $this->countLevel(Level::Alert));

        $this->fail(); // 4º intento: el middleware ve 3 fallos previos
        $this->assertTrue($this->securityLog()->hasAlertThatContains('Possible brute force'));
    }

    public function test_cinco_fallos_bloquean_en_el_intento_siguiente(): void
    {
        foreach (range(1, 5) as $i) {
            $this->fail()->assertStatus(302);
        }

        // 6º intento: bloqueo + CRITICAL, aunque la contraseña sea correcta
        $this->post('/login', ['email' => 'prueba@monitor.test', 'password' => 'Correcta123!'])
            ->assertStatus(429);

        $this->assertGuest();
        $this->assertTrue($this->securityLog()->hasCriticalThatContains('Temporary IP block'));
    }

    public function test_ip_bloqueada_recibe_429_y_registro_blocked(): void
    {
        foreach (range(1, 5) as $i) {
            $this->fail();
        }
        $this->fail()->assertStatus(429);  // activa el bloqueo
        $this->fail()->assertStatus(429);  // ya bloqueada -> blocked

        $this->assertDatabaseHas('login_attempts', ['status' => 'blocked']);
        $this->assertTrue($this->securityLog()->hasWarningThatContains('Blocked login attempt'));
    }

    public function test_despues_de_diez_minutos_se_vuelve_a_admitir(): void
    {
        foreach (range(1, 5) as $i) {
            $this->fail();
        }
        $this->fail()->assertStatus(429);

        $this->travel(11)->minutes();

        $this->post('/login', ['email' => 'prueba@monitor.test', 'password' => 'Correcta123!'])
            ->assertRedirect('/servidor');
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_el_login_existente_sigue_funcionando_y_no_guarda_datos_sensibles(): void
    {
        $this->fail();

        $row = LoginAttempt::first();
        $this->assertSame(hash('sha256', 'prueba@monitor.test'), $row->identifier_hash);
        $this->assertStringNotContainsString('incorrecta', json_encode(LoginAttempt::all()->toArray()));
    }

    // ---- Mejora 1: detección por cuenta ------------------------------------

    public function test_mejora_deteccion_por_cuenta_desde_ips_distintas(): void
    {
        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.$i"])->fail();
        }

        // Ninguna IP llegó a 5 fallos, pero la cuenta sí -> se bloquea la cuenta
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->post('/login', ['email' => 'prueba@monitor.test', 'password' => 'Correcta123!'])
            ->assertStatus(429);

        $this->assertTrue($this->securityLog()->hasCriticalThatContains('Temporary account block'));

        // Otra cuenta desde esa misma IP no se ve afectada
        User::create(['name' => 'Otro', 'email' => 'otro@monitor.test', 'password' => 'Otro1234!']);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->post('/login', ['email' => 'otro@monitor.test', 'password' => 'Otro1234!'])
            ->assertRedirect('/servidor');
    }

    // ---- Panel protegido + Mejora 2: exportación de evidencias -------------

    public function test_panel_requiere_login_y_rol_admin(): void
    {
        $this->get('/seguridad')->assertRedirect('/login');

        $this->actingAs($this->user)->get('/seguridad')->assertForbidden();

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@monitor.test', 'password' => 'Admin1234!']);
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get('/seguridad')
            ->assertOk()
            ->assertSee('Panel de seguridad');
    }

    public function test_mejora_exportacion_de_evidencias_con_hash(): void
    {
        $this->fail(); $this->fail();

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@monitor.test', 'password' => 'Admin1234!']);
        $admin->forceFill(['is_admin' => true])->save();

        $response = $this->actingAs($admin)->get('/seguridad/exportar');
        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('ip_address', $csv);
        $this->assertSame(3, substr_count(trim($csv), "\n") + 1); // encabezado + 2 filas
        $this->assertSame(hash('sha256', $csv), $response->headers->get('X-Evidence-SHA256'));
        $this->assertTrue($this->securityLog()->hasNoticeThatContains('Evidence export'));
    }

    public function test_cerrar_sesion(): void
    {
        $this->actingAs($this->user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
