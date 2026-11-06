# TP 3 · Logging y detección de incidentes

**Programación IV · UTN FRRe · Cohorte 2026** — Proyecto: `monitor-servidor`

## 1. Datos de la integración

| Pregunta del TP | Respuesta |
|---|---|
| Versión | Laravel 13 (`php artisan --version`) |
| ¿Qué autenticador usa el proyecto? | El proyecto (TP 1 y 2) **no tenía login**. Se agregó uno mínimo con el guard `web` de sesión de Laravel (`Auth::attempt()` en `AuthController`), sin Breeze/Jetstream. `Auth::attempt()` dispara solo los eventos `Login` y `Failed`. |
| ¿En qué ruta se integró el middleware? | `POST /login` (nombre `login.attempt`) en `routes/web.php`. Solo esa ruta. |
| ¿Cómo se registra el listener? | Por *event discovery* de Laravel: `app/Listeners/RecordSecurityAuthentication.php` con type-hint `Login|Failed`. No se agregó `Event::listen()` para no registrarlo dos veces (`php artisan event:list` lo muestra una sola vez por evento). |
| Base de datos / caché | SQLite (`database/database.sqlite`) y `CACHE_STORE=database` (persistente). |
| Rutas existentes | `/servidor`, `/graficos` y `POST /demo/abrir/{app}` no se modificaron. |

## 2. Archivos

| Tipo | Archivo |
|---|---|
| Canal de logs | `config/logging.php` → canal `security` (daily, nivel `info`, 30 días) |
| Migración | `database/migrations/2026_11_05_200000_create_login_attempts_table.php` |
| Migración | `database/migrations/2026_11_05_200001_add_is_admin_to_users_table.php` (rol para el panel) |
| Modelo | `app/Models/LoginAttempt.php` |
| Listener | `app/Listeners/RecordSecurityAuthentication.php` (INFO / WARNING + registro en BD) |
| Middleware | `app/Http/Middleware/SecurityLoginGuard.php` (ALERT, bloqueo, CRITICAL, 429) |
| Login | `app/Http/Controllers/AuthController.php`, `resources/views/auth/login.blade.php` |
| Panel | `app/Http/Controllers/SecurityDashboardController.php`, `resources/views/seguridad/panel.blade.php` |
| Permiso | `Gate viewSecurityDashboard` en `app/Providers/AppServiceProvider.php` |
| Seeders | `SecurityUsersSeeder` (usuarios de prueba), `SecurityDemoSeeder` (datos simulados) |
| Pruebas | `tests/Feature/SecurityLoggingTest.php` |

## 3. Reglas implementadas

| Condición | Respuesta | Dónde |
|---|---|---|
| Login correcto | INFO `Authentication success` + registro `success` | Listener |
| Cada fallo (1–2) | WARNING `Authentication failed` + registro `failed` | Listener |
| 3–4 fallos por IP en 5 min | ALERT `Possible brute force` en el intento siguiente | Middleware |
| 5 fallos por IP en 5 min | Bloqueo 10 min (caché) + CRITICAL `Temporary IP block` + HTTP 429 | Middleware |
| IP bloqueada | HTTP 429 + WARNING `Blocked login attempt` + registro `blocked` | Middleware |

Niveles según RFC 5424: ALERT (1) y CRITICAL (2) son más graves que WARNING (4) e INFO (6).

**Detectar vs. bloquear:** el middleware evalúa el historial *antes* de cada intento. Por eso el quinto fallo se registra normalmente y recién el **sexto** intento se bloquea (aunque traiga la contraseña correcta). Detectar es registrar y clasificar (WARNING/ALERT) sin cortar el acceso; bloquear es la respuesta automática (429). En tiempo real se bloquearía en el mismo quinto intento, evaluando después del fallo.

## 4. Pruebas obligatorias

Automatizadas en `tests/Feature/SecurityLoggingTest.php` (`php artisan test --filter=SecurityLoggingTest`):

| Prueba | Resultado esperado | Test |
|---|---|---|
| Login válido | INFO y registro success | `test_login_valido_registra_info_y_success` |
| Dos contraseñas incorrectas | Dos WARNING y dos failed | `test_dos_contrasenas_incorrectas_...` |
| Tres fallos en 5 min | ALERT en el intento siguiente | `test_tres_fallos_generan_alert_...` |
| Cinco fallos en 5 min | Bloqueo 10 min en el intento siguiente | `test_cinco_fallos_bloquean_...` |
| Acceso con IP bloqueada | HTTP 429 y registro blocked | `test_ip_bloqueada_recibe_429_...` |
| Después de 10 minutos | Vuelve a admitirse | `test_despues_de_diez_minutos_...` |
| El login sigue funcionando y no guarda contraseñas | — | `test_el_login_existente_sigue_funcionando_...` |

Los tests usan SQLite en memoria y caché `array` (ver `phpunit.xml`), así que no tocan la base real.

## 5. Consultas

SQLite (la base del proyecto). `localtime` porque la app guarda la hora de Argentina:

```sql
-- Últimos eventos
SELECT * FROM login_attempts ORDER BY created_at DESC LIMIT 20;

-- Fallos acumulados por IP
SELECT ip_address, COUNT(*) AS total_fallos
FROM login_attempts WHERE status = 'failed'
GROUP BY ip_address ORDER BY total_fallos DESC;

-- IP sospechosas: 3+ fallos en 5 minutos
SELECT ip_address, COUNT(*) AS total_fallos
FROM login_attempts
WHERE status = 'failed'
  AND created_at >= datetime('now', 'localtime', '-5 minutes')
GROUP BY ip_address
HAVING COUNT(*) >= 3
ORDER BY total_fallos DESC;
```

Equivalente MySQL (el del enunciado): `AND created_at >= NOW() - INTERVAL 5 MINUTE`.

## 6. Análisis y toma de decisiones

Sobre los datos simulados de `SecurityDemoSeeder` (IPs de documentación RFC 5737):

**¿Qué IP concentra más fallos?**
`203.0.113.50`: 8 fallos en unos 2 minutos, seguidos de 3 intentos `blocked`. Le siguen `198.51.100.23` (3 fallos) y `192.0.2.10` (1 fallo).

**¿Puede afirmarse que existe un ataque?**
No con certeza, pero `203.0.113.50` tiene el patrón típico de fuerza bruta / *password spraying*: muchos intentos en poco tiempo, contra **cuatro cuentas distintas** (incluidas `root` y `soporte`, que no existen), con user-agent `python-requests` (un script, no un navegador) y sin ningún éxito. Es un incidente probable que justifica el bloqueo automático y una revisión. En cambio `198.51.100.23` hizo 3 fallos sobre **una sola cuenta** con navegador normal y después entró: es un usuario que se equivocó de contraseña, no un ataque, aunque dispare un ALERT. Por eso el umbral solo no alcanza para afirmar un ataque: hay que mirar el contexto.

**¿Qué consecuencias tendría bloquear una IP compartida?**
`192.0.2.10` representa un laboratorio, una oficina o una red con NAT (por ejemplo, la facultad o un proveedor móvil): muchas personas salen con la misma IP. Si un atacante (o varios alumnos que se equivocan) suma 5 fallos, se bloquea a **todos** durante 10 minutos: es una denegación de servicio causada por nuestra propia defensa. Además, un atacante podría provocarla a propósito. Por eso conviene combinar límites por IP y por cuenta, umbrales más altos para IPs conocidas y configurar *trusted proxies* (detrás de un proxy, `$request->ip()` devolvería la IP del proxy y se bloquearía a todos).

**¿Qué otros indicadores usaría el equipo de seguridad?**
- Cantidad de cuentas distintas probadas desde una IP (spraying).
- Fallos por cuenta desde muchas IPs (ataque distribuido) — implementado como mejora.
- User-agent (scripts, herramientas conocidas), horario inusual, geolocalización / reputación de la IP.
- Intentos sobre cuentas inexistentes o privilegiadas (`admin`, `root`).
- Un éxito justo después de muchos fallos (posible contraseña adivinada).
- Correlación con otros logs (servidor web, firewall) en un SIEM como Wazuh o Splunk.

**¿Qué acciones deben ser automáticas y cuáles requieren revisión humana?**
- *Automáticas* (reversibles, rápidas, de bajo costo): registrar, clasificar con su nivel, el bloqueo temporal de 10 minutos, notificar.
- *Revisión humana* (alto impacto o difíciles de revertir): bloqueo permanente de una IP o rango, bloquear o resetear una cuenta, decidir si es un incidente real, avisar a usuarios, ajustar umbrales, y cualquier decisión sobre IPs compartidas (no bloquear una red entera de forma automática).

## 7. Actividad integradora: dos mejoras

**1. Detección por cuenta** (`SecurityLoginGuard`). Si un mismo email acumula 5 fallos en 15 minutos, **aunque vengan de IPs distintas**, se bloquea esa cuenta 10 minutos (CRITICAL `Temporary account block` + 429). Cubre el ataque distribuido que el límite por IP no ve. La cuenta se identifica por el hash SHA-256 del email, no por el email. Se usa el email también en los éxitos (el enunciado usa el ID del usuario), para que fallos y éxitos de una misma cuenta tengan el mismo hash y se puedan correlacionar. Riesgo: un atacante podría bloquear la cuenta de otra persona, por eso es temporal. Test: `test_mejora_deteccion_por_cuenta_desde_ips_distintas`.

**2. Exportación de evidencias** (`GET /seguridad/exportar`, solo administradores). Descarga un CSV con los intentos de las últimas 24 h. Calcula el SHA-256 del archivo, lo envía en el encabezado `X-Evidence-SHA256` y lo deja escrito en el log de seguridad (NOTICE `Evidence export`, con usuario, cantidad de filas y hash). Así después se puede comprobar que el archivo entregado no fue modificado (cadena de custodia básica). Test: `test_mejora_exportacion_de_evidencias_con_hash`.

## 8. Decisión de seguridad adoptada y límites

Se adoptó un esquema de **detección escalonada con bloqueo temporal automático**: WARNING por cada fallo, ALERT a partir del tercero, bloqueo de 10 minutos al quinto por IP o por cuenta, y panel con acceso restringido a administradores (`auth` + `can:viewSecurityDashboard`) para la revisión humana. Se guarda el hash del identificador y nunca contraseñas, tokens ni códigos 2FA.

Es un prototipo educativo: el conteo y el bloqueo no son atómicos (dos pedidos simultáneos pueden pasar el umbral), no hay *trusted proxies* configurados y los logs no están protegidos contra modificación. En producción: `RateLimiter` con Redis, límites por IP y cuenta, proxies confiables, política de retención y almacenamiento protegido de auditoría.

## 9. Evidencias (completar con capturas)

- [ ] `php artisan test --filter=SecurityLoggingTest` en verde
- [ ] `php artisan event:list` mostrando el listener una vez para `Login` y otra para `Failed`
- [ ] `storage/logs/security-AAAA-MM-DD.log` con INFO, WARNING, ALERT y CRITICAL
- [ ] Página 429 al intentar loguearse con la IP bloqueada
- [ ] Resultado de las consultas SQL
- [ ] Panel `/seguridad` con los indicadores y la tabla de IPs
- [ ] `/seguridad` con el usuario común → 403
