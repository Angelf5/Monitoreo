<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel de seguridad · Monitor de Servidor</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f5f7; margin: 0; padding: 24px; }
        h1 { margin-top: 0; }
        h2 { font-size: 18px; margin-top: 32px; }
        a { color: #1d5fa8; }
        .top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
        .card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .label { color: #666; font-size: 14px; }
        .value { font-size: 28px; font-weight: 600; margin-top: 4px; }
        .ok { color: #15803d; } .warn { color: #b45309; } .bad { color: #b91c1c; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,.1); font-size: 14px; }
        th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #eee; }
        th { background: #f0f2f5; }
        .badge { padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; }
        .b-success { background: #e7f6ec; color: #15803d; }
        .b-failed { background: #fff4e5; color: #b45309; }
        .b-blocked, .b-critico { background: #fdecec; color: #b91c1c; }
        .b-alerta { background: #fff4e5; color: #b45309; }
        .btn { background: #1d5fa8; color: #fff; border: 0; border-radius: 8px; padding: 10px 16px; text-decoration: none;
               font-size: 14px; cursor: pointer; }
        .muted { color: #666; font-size: 13px; }
        form { display: inline; }
    </style>
</head>
<body>
<div class="top">
    <h1>Panel de seguridad</h1>
    <div>
        <a class="btn" href="{{ route('seguridad.export') }}">Exportar evidencias (CSV)</a>
        <a href="/servidor" style="margin: 0 12px">Volver al monitor</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn" type="submit">Salir</button></form>
    </div>
</div>

<p class="muted">Últimas 24 horas · Sesión: {{ auth()->user()->email }}</p>

<div class="grid">
    <div class="card"><div class="label">Exitosos</div><div class="value ok">{{ $stats['success'] }}</div></div>
    <div class="card"><div class="label">Fallidos</div><div class="value warn">{{ $stats['failed'] }}</div></div>
    <div class="card"><div class="label">Bloqueados</div><div class="value bad">{{ $stats['blocked'] }}</div></div>
</div>

<h2>IPs sospechosas (3 o más fallos en 5 minutos)</h2>
<table>
    <tr><th>IP</th><th>Fallos</th><th>Clasificación</th></tr>
    @forelse ($suspiciousIps as $item)
        <tr>
            <td>{{ $item->ip_address }}</td>
            <td>{{ $item->total }}</td>
            <td>
                @if ($item->total >= 5)
                    <span class="badge b-critico">CRÍTICO</span>
                @else
                    <span class="badge b-alerta">ALERTA</span>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="3" class="muted">Sin IPs sospechosas en este momento.</td></tr>
    @endforelse
</table>

<h2>IPs con más fallos (24 h)</h2>
<table>
    <tr><th>IP</th><th>Fallos</th></tr>
    @forelse ($topIps as $item)
        <tr><td>{{ $item->ip_address }}</td><td>{{ $item->total }}</td></tr>
    @empty
        <tr><td colspan="2" class="muted">Sin fallos registrados.</td></tr>
    @endforelse
</table>

<h2>Últimos 20 eventos</h2>
<table>
    <tr><th>Fecha</th><th>IP</th><th>Estado</th><th>Cuenta (hash)</th><th>User-Agent</th></tr>
    @forelse ($latest as $row)
        <tr>
            <td>{{ $row->created_at?->format('d/m/Y H:i:s') }}</td>
            <td>{{ $row->ip_address }}</td>
            <td><span class="badge b-{{ $row->status }}">{{ $row->status }}</span></td>
            <td><code>{{ $row->identifier_hash ? substr($row->identifier_hash, 0, 12).'…' : '—' }}</code></td>
            <td class="muted">{{ \Illuminate\Support\Str::limit($row->user_agent, 50) }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">Todavía no hay eventos.</td></tr>
    @endforelse
</table>
</body>
</html>
