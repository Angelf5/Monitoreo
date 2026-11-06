<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuarios de PRUEBA para el TP 3 (solo entorno local).
 *   admin@monitor.test   / Admin1234!    -> puede ver /seguridad
 *   usuario@monitor.test / Usuario1234!  -> puede loguearse, pero /seguridad da 403
 */
class SecurityUsersSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@monitor.test'],
            ['name' => 'Administrador', 'password' => 'Admin1234!']
        );
        $admin->forceFill(['is_admin' => true])->save();

        User::updateOrCreate(
            ['email' => 'usuario@monitor.test'],
            ['name' => 'Usuario común', 'password' => 'Usuario1234!']
        );
    }
}
