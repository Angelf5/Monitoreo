<?php

namespace App\Http\Controllers;

use App\Events\AppOpened;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    // Lista blanca: el navegador elige una CLAVE, nunca el comando
    private const APPS = [
        'calculadora' => 'calc.exe',
        'bloc'        => 'notepad.exe',
        'paint'       => 'mspaint.exe', // desafío opcional
    ];

    public function abrir(Request $request, string $app)
    {
        // Solo funciona si la demo está activada y el servidor es Windows
        if (! config('app.demo_apps') || PHP_OS_FAMILY !== 'Windows') {
            abort(403, 'La demo está desactivada.');
        }

        $exe = self::APPS[$app] ?? abort(404);

        // Abre el programa en el servidor sin esperar a que se cierre
        pclose(popen('start "" ' . $exe, 'r'));

        // Avisa a todos los navegadores conectados
        AppOpened::dispatch($app, $request->ip(), now()->format('H:i:s'));

        return response()->json(['ok' => true]);
    }
}
