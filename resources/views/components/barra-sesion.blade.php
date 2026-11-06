{{-- Barra de sesión: usuario actual, acceso al panel (solo admin) y botón para cerrar sesión. --}}
<div style="display:flex; justify-content:flex-end; align-items:center; gap:12px; flex-wrap:wrap;
            margin-bottom:16px; font-size:14px;">
    @auth
        <span style="color:#666">{{ auth()->user()->email }}</span>
        @can('viewSecurityDashboard')
            <a href="{{ route('seguridad.panel') }}"
               style="background:#15803d; color:#fff; border:0; border-radius:8px; padding:8px 14px;
                      font-size:14px; text-decoration:none; display:inline-block; line-height:normal;">
                Panel de seguridad
            </a>
        @endcan
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf
            <button type="submit"
                    style="background:#b91c1c; color:#fff; border:0; border-radius:8px; padding:8px 14px;
                           font-size:14px; cursor:pointer;">
                Cerrar sesión
            </button>
        </form>
    @else
        <a href="{{ route('login') }}" style="color:#1d5fa8">Iniciar sesión</a>
    @endauth
</div>
