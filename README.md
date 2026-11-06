# Monitor de Servidor en Tiempo Real (TP 2)

**Programación IV · UTN FRRe · Cohorte 2026**

Evolución del TP 1: las métricas del servidor ya no se piden con polling, sino que el servidor las **empuja** cada 5 segundos por un WebSocket (Laravel Reverb + Laravel Echo). Se agregan botones que abren la Calculadora, el Bloc de notas y Paint en el servidor (con lista blanca), una lista de actividad en vivo y una vista `/graficos` con Chart.js.

## Cómo levantarlo

```powershell
cd C:\xampp\htdocs\monitor-servidor
composer install
npm install
```

Cuatro terminales en la carpeta del proyecto:

| Terminal | Comando                            | Qué hace                              |
|----------|------------------------------------|---------------------------------------|
| 1        | `php artisan reverb:start --debug` | Servidor WebSocket (puerto 8080)      |
| 2        | `php artisan schedule:work`        | Ejecuta `metrics:broadcast` cada 5 s  |
| 3        | `npm run dev`                      | Compila y sirve el JavaScript (Echo)  |
| 4        | `php artisan serve`                | Servidor web (puerto 8000)            |

- Monitor: http://127.0.0.1:8000/servidor
- Gráficos: http://127.0.0.1:8000/graficos

## Estructura (TP 2)

| Parte        | Archivo                                                 | Responsabilidad                                    |
|--------------|---------------------------------------------------------|----------------------------------------------------|
| Evento       | `app/Events/MetricsUpdated.php`                         | Envía las métricas por el canal `server.metrics`   |
| Evento       | `app/Events/AppOpened.php`                              | Avisa quién abrió un programa (canal `server.demo`)|
| Comando      | `app/Console/Commands/BroadcastMetrics.php`             | Lee `ServerMetrics` y dispara `MetricsUpdated`     |
| Scheduler    | `routes/console.php`                                    | Programa el comando cada 5 segundos                |
| Controlador  | `app/Http/Controllers/DemoController.php`               | Abre programas de una lista blanca                 |
| Rutas        | `routes/web.php`                                        | `/servidor`, `/graficos`, `POST /demo/abrir/{app}` |
| Vistas       | `resources/views/servidor.blade.php`, `graficos.blade.php` | Escuchan con Echo y actualizan la página        |
| Componente   | `resources/views/components/grafico-metrica.blade.php`  | Tarjeta + `<canvas>` reutilizable                  |
| JavaScript   | `resources/js/echo.js`, `resources/js/app.js`           | Configura Echo y expone Chart.js                   |

## Desafío opcional

Se agregó un tercer botón para abrir **Paint** (`mspaint.exe`), tocando los tres lugares: la constante `APPS` del controlador, el `whereIn` de la ruta y la vista.

## Respuestas a las preguntas

**1. ¿Qué diferencia ven en la pestaña Red entre el TP 1 (polling) y este TP?**
En el TP 1 aparecía un pedido HTTP nuevo a `/api/metrics` cada 5 segundos, cada uno con su request, su respuesta y sus encabezados. En el TP 2 hay **una sola conexión WebSocket** (filtro WS) que queda abierta, y dentro de ella, en la sección *Mensajes*, llega un mensaje nuevo cada 5 segundos. El navegador no pide nada: solo recibe.

**2. ¿Qué pasa con la vista si detienen `reverb:start`? ¿Y si detienen `schedule:work`?**
Si se detiene **Reverb**, se corta el WebSocket: el indicador pasa a "Sin conexión", los valores dejan de cambiar y los botones abren el programa pero la actividad no llega a nadie (además, el `dispatch` del evento falla porque Laravel no encuentra a Reverb). Pusher-js reintenta solo y, cuando Reverb vuelve, se reconecta.
Si se detiene el **scheduler**, la conexión sigue abierta ("Conectado"), pero nadie ejecuta `metrics:broadcast`, así que no llegan métricas nuevas y los valores quedan congelados. Los botones y la actividad siguen funcionando, porque `AppOpened` se dispara desde el controlador y no depende del scheduler.

**3. ¿Por qué PowerShell se ejecuta la misma cantidad de veces con 1 o con 30 personas mirando?**
Porque las métricas ya no las pide cada navegador. Las lee **un solo proceso** (el scheduler), cada 5 segundos, es decir 12 veces por minuto. Reverb toma ese único mensaje y lo reparte a todas las conexiones abiertas. La cantidad de espectadores solo cambia a cuántos se les reenvía el mensaje, no cuántas veces se lee el sistema.

**4. ¿Por qué `DemoController` usa una lista blanca en lugar de recibir el nombre del programa desde el navegador?**
Porque `popen()` ejecuta comandos en el servidor con los permisos de PHP. Si el nombre del programa viniera del navegador, cualquiera en la red podría mandar `cmd /c del ...` o descargar y ejecutar malware: eso es **ejecución remota de comandos (RCE)**, una de las vulnerabilidades más graves. Con la lista blanca el navegador solo elige una clave (`calculadora`, `bloc`, `paint`) y el servidor la traduce a un ejecutable fijo; cualquier otra clave da 404, tanto en la ruta (`whereIn`) como en el controlador. Además hay doble protección: la demo se activa a mano con `DEMO_APPS=true` y el `throttle:2,1` limita los clics.

**5. ¿Qué diferencia hay entre `ShouldBroadcast` y `ShouldBroadcastNow`?**
`ShouldBroadcast` no envía el evento en el momento: lo pone en la **cola** (`QUEUE_CONNECTION`, en este proyecto `database`) y hace falta un proceso `php artisan queue:work` para que lo saque y lo mande a Reverb. Sirve para no demorar la respuesta HTTP cuando el envío es lento. `ShouldBroadcastNow` lo envía **inmediatamente**, en el mismo proceso que lo dispara, sin pasar por la cola. Acá se usa `Now` para no tener que levantar un quinto proceso y para que la actividad aparezca al instante.

**6. ¿Por qué la vista de gráficos no necesitó ningún cambio en el servidor? ¿Qué ventaja tiene haber usado un componente?**
Porque escucha el **mismo canal** (`server.metrics`) y el mismo evento (`MetricsUpdated`) que el monitor. Reverb ya envía cada mensaje a todas las páginas suscriptas a ese canal, así que agregar otra pantalla es solo agregar otro "oyente": el servidor no sabe ni le importa cuántas vistas hay. Esto desacopla quien produce los datos de quien los muestra.
El componente `<x-grafico-metrica>` permite escribir la tarjeta y el `<canvas>` **una sola vez** y usarla tres veces cambiando solo los parámetros (`titulo`, `campo`, `color`, `tipo`). Si hay que cambiar el diseño se toca un solo archivo, y agregar una métrica nueva es una línea más en la vista.

## Capturas

- Página de inicio (`/`): _(agregar captura)_
- `/servidor` con "Conectado" y actividad: _(agregar captura)_
- `/graficos` con al menos un minuto de datos: _(agregar captura)_
- Pestaña Red, filtro WS, con los mensajes: _(agregar captura)_

---

# TP 1 · Monitor de Servidor con Laravel

**Programación IV · UTN FRRe · Cohorte 2026**

App Laravel que lee datos del sistema operativo del servidor (nombre del equipo, sistema operativo, versión de PHP, disco, memoria, CPU, uptime y temperatura) y los muestra en una vista web (`/servidor`) y en una API JSON (`/api/metrics`). La vista se actualiza sola cada 5 segundos (polling).

### Sistema operativo usado

Windows (laptop personal) con XAMPP, PHP 8.4.25 y Composer.

### Cómo levantarlo

```powershell
cd C:\xampp\htdocs\monitor-servidor
composer install
php artisan migrate
php artisan serve
```

- Vista web: http://127.0.0.1:8000/servidor
- API JSON: http://127.0.0.1:8000/api/metrics

### Estructura

| Parte       | Archivo                                       | Responsabilidad                      |
|-------------|-----------------------------------------------|--------------------------------------|
| Service     | `app/Services/ServerMetrics.php`              | Lee el sistema operativo             |
| Controlador | `app/Http/Controllers/ServerController.php`   | Devuelve la vista o el JSON          |
| Vista       | `resources/views/servidor.blade.php`          | Arma las tarjetas y hace el polling  |
| Rutas       | `routes/web.php`, `routes/api.php`            | `/servidor` y `/api/metrics`         |

### Respuestas a las preguntas

**1. ¿Por qué la temperatura suele aparecer como "No disponible" en Windows?**
La clase WMI `MSAcpi_ThermalZoneTemperature` (en el namespace `root/wmi`) solo se puede leer con permisos de administrador. Si la terminal donde corre `php artisan serve` no se abrió como administrador, PowerShell da error de acceso, el `catch` lo ignora y la temperatura queda en `null`. Además, aunque se abra como administrador, muchas placas madre (sobre todo en notebooks) no informan este dato por ACPI o devuelven un valor fijo, así que igual puede seguir apareciendo "No disponible".

**2. ¿Por qué el script de PowerShell nunca debe incluir datos que vengan del usuario?**
Porque `shell_exec()` ejecuta comandos en el servidor con los permisos del proceso de PHP. Si se arma el comando con datos de un formulario, de la URL, etc., un atacante podría meter sus propias instrucciones (inyección de comandos) y ejecutar cualquier cosa en el servidor: borrar archivos, leer credenciales, instalar malware o tomar el control del equipo. Por eso el script es fijo y no tiene ninguna parte variable.

**3. ¿Qué riesgo tiene dejar `/api/metrics` sin autenticación en un servidor real?**
- **Exposición de información:** cualquiera puede ver el nombre del equipo, el sistema operativo y la versión exacta de PHP, datos que sirven para buscar vulnerabilidades conocidas de esas versiones y planificar un ataque.
- **Denegación de servicio:** cada pedido abre un proceso de PowerShell, que es costoso. Muchos pedidos a la vez (o un ataque automatizado) pueden saturar la CPU y la memoria del servidor. El `throttle:60,1` limita a 60 pedidos por minuto por IP, pero no frena a muchos clientes distintos.
- **Monitoreo del servidor por terceros:** se puede seguir la carga y saber cuándo el servidor está más ocupado o cuándo se reinició (uptime).

Se soluciona protegiendo la ruta con autenticación, por ejemplo `->middleware('auth:sanctum')`.

**4. Con polling cada 5 segundos, ¿cuántos pedidos por minuto recibe el servidor si hay 50 personas mirando?**
Cada persona hace 60 / 5 = 12 pedidos por minuto, así que 50 × 12 = **600 pedidos por minuto** (10 por segundo), aunque los datos no hayan cambiado. Además cada pedido ejecuta PowerShell, lo que multiplica la carga.

**5. Si una app solo necesita la temperatura, ¿qué datos recibe de más al llamar a `/api/metrics`?**
Recibe todo lo demás: `hostname`, `os`, `php`, `disk` (`total_gb`, `free_gb`, `used_pct`), `memory` (`total_mb`, `available_mb`, `used_pct`), `cpu_pct`, `uptime_s` y `at`. Solo le sirve `cpu_temp_c`. Esto se llama *over-fetching*, y es el problema que resuelve GraphQL, donde el cliente pide solo los campos que necesita.

### Capturas

- Vista `/servidor`: _(agregar captura)_
- Respuesta de `/api/metrics`: _(agregar captura)_

---

# TP 3 · Logging y detección de incidentes

Módulo de seguridad integrado sobre este mismo proyecto. Detalle, análisis y respuestas en **[INFORME_TP3_Seguridad.md](INFORME_TP3_Seguridad.md)**.

## Cómo ponerlo en marcha

```powershell
cd C:\xampp\htdocs\monitor-servidor
php artisan config:clear
php artisan migrate
php artisan db:seed --class=SecurityUsersSeeder
php artisan event:list
php artisan test --filter=SecurityLoggingTest
php artisan serve
```

| URL | Qué es |
|---|---|
| http://127.0.0.1:8000/login | Login (con el middleware `SecurityLoginGuard`) |
| http://127.0.0.1:8000/seguridad | Panel de seguridad (solo admin) |
| http://127.0.0.1:8000/seguridad/exportar | CSV de evidencias con hash SHA-256 |

Usuarios de prueba (solo local): `admin@monitor.test` / `Admin1234!` (ve el panel) y `usuario@monitor.test` / `Usuario1234!` (403 en el panel).

Datos simulados para el análisis: `php artisan db:seed --class=SecurityDemoSeeder`.

Log de seguridad: `storage/logs/security-AAAA-MM-DD.log`. En PowerShell: `Get-Content storage\logs\security-*.log -Wait -Tail 20`.

Para repetir las pruebas manuales sin esperar 10 minutos (solo en local): `php artisan tinker --execute="App\Models\LoginAttempt::truncate(); Cache::flush();"`.
