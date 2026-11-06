<?php

namespace App\Services;

class ServerMetrics
{
    public function snapshot(): array
    {
        $win = $this->readWindows();

        return [
            'hostname'   => gethostname(),
            'os'         => php_uname('s') . ' ' . php_uname('r'),
            'php'        => PHP_VERSION,
            'disk'       => $this->disk(),
            'memory'     => $this->memory($win),
            'cpu_pct'    => $win['cpu_pct'] ?? null,
            'uptime_s'   => $win['uptime_s'] ?? null,
            'cpu_temp_c' => $win['temp_c'] ?? null,
            'at'         => now()->toIso8601String(),
        ];
    }

    private function disk(): array
    {
        $path  = base_path();
        $total = disk_total_space($path);
        $free  = disk_free_space($path);

        return [
            'total_gb' => round($total / 1e9, 1),
            'free_gb'  => round($free / 1e9, 1),
            'used_pct' => round(100 * ($total - $free) / $total, 1),
        ];
    }

    private function memory(?array $win): ?array
    {
        if (empty($win['mem_total_kb'])) {
            return null;
        }

        $total = $win['mem_total_kb'];
        $free  = $win['mem_free_kb'];

        return [
            'total_mb'     => intdiv($total, 1024),
            'available_mb' => intdiv($free, 1024),
            'used_pct'     => round(100 * ($total - $free) / $total, 1),
        ];
    }

    // Ejecuta un script de PowerShell y devuelve sus datos como array
    private function readWindows(): ?array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $script = <<<'PS'
            $os  = Get-CimInstance Win32_OperatingSystem
            $cpu = (Get-CimInstance Win32_Processor |
                Measure-Object -Property LoadPercentage -Average).Average
            $temp = $null
            # 1) Contador de rendimiento: no necesita permisos de administrador (valor en Kelvin)
            try {
                $z = Get-CimInstance -ClassName Win32_PerfFormattedData_Counters_ThermalZoneInformation -ErrorAction Stop |
                    Where-Object { $_.Temperature -gt 0 } |
                    Sort-Object Temperature -Descending | Select-Object -First 1
                if ($z) { $temp = [math]::Round($z.Temperature - 273.15, 1) }
            } catch {}
            # 2) Respaldo: WMI de ACPI (solo responde con PowerShell como administrador)
            if ($null -eq $temp) { try {
                $ns  = 'root/wmi'
                $cls = 'MSAcpi_ThermalZoneTemperature'
                $t = Get-CimInstance -Namespace $ns -ClassName $cls -ErrorAction Stop |
                    Select-Object -First 1
                if ($t) { $temp = [math]::Round($t.CurrentTemperature / 10 - 273.15, 1) }
            } catch {} }
            [pscustomobject]@{
                mem_total_kb = [int64]$os.TotalVisibleMemorySize
                mem_free_kb  = [int64]$os.FreePhysicalMemory
                cpu_pct      = $cpu
                uptime_s     = [int64]((Get-Date) - $os.LastBootUpTime).TotalSeconds
                temp_c       = $temp
            } | ConvertTo-Json -Compress
            PS;

        $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
        $output  = shell_exec("powershell -NoProfile -NonInteractive -EncodedCommand $encoded");

        return $output ? json_decode($output, true) : null;
    }
}
