<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SecurityLoginGuard;
use App\Models\LoginAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityDashboardController extends Controller
{
    public function index(): View
    {
        $since = now()->subDay();

        $stats = [];
        foreach (['success', 'failed', 'blocked'] as $status) {
            $stats[$status] = LoginAttempt::where('status', $status)
                ->where('created_at', '>=', $since)
                ->count();
        }

        // IPs con 3 o más fallos en los últimos 5 minutos
        $suspiciousIps = LoginAttempt::select('ip_address', DB::raw('COUNT(*) as total'))
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes(SecurityLoginGuard::IP_WINDOW_MINUTES))
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) >= ?', [SecurityLoginGuard::IP_ALERT_AT])
            ->orderByDesc('total')
            ->get();

        // Ranking de IPs con más fallos en 24 h (para el análisis)
        $topIps = LoginAttempt::select('ip_address', DB::raw('COUNT(*) as total'))
            ->where('status', 'failed')
            ->where('created_at', '>=', $since)
            ->groupBy('ip_address')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $latest = LoginAttempt::orderByDesc('created_at')->orderByDesc('id')->limit(20)->get();

        return view('seguridad.panel', compact('stats', 'suspiciousIps', 'topIps', 'latest'));
    }

    /**
     * Mejora (actividad integradora): exportación de evidencias.
     * Descarga un CSV con los intentos de las últimas 24 h. El hash SHA-256 del archivo
     * queda en el log de seguridad, así después se puede probar que no fue modificado.
     */
    public function export(): StreamedResponse
    {
        $rows = LoginAttempt::where('created_at', '>=', now()->subDay())
            ->orderBy('created_at')
            ->get(['id', 'created_at', 'ip_address', 'status', 'identifier_hash', 'user_agent']);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['id', 'created_at', 'ip_address', 'status', 'identifier_hash', 'user_agent'], escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->id,
                $row->created_at?->format('Y-m-d H:i:s'),
                $row->ip_address,
                $row->status,
                $row->identifier_hash,
                $row->user_agent,
            ], escape: '');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $sha256 = hash('sha256', $csv);
        $filename = 'evidencias-login-'.now()->format('Ymd-His').'.csv';

        Log::channel('security')->notice('Evidence export', [
            'user_id' => auth()->id(),
            'file' => $filename,
            'rows' => $rows->count(),
            'sha256' => $sha256,
        ]);

        return response()->streamDownload(fn () => print($csv), $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Evidence-SHA256' => $sha256,
        ]);
    }
}
