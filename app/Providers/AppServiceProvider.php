<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // TP 3: solo los administradores pueden ver el panel de seguridad.
        Gate::define('viewSecurityDashboard', fn (User $user) => (bool) $user->is_admin);

        // El listener RecordSecurityAuthentication NO se registra acá con Event::listen():
        // Laravel lo descubre solo en app/Listeners. Registrarlo también acá lo duplicaría.
    }
}
