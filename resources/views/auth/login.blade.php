<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Monitor de Servidor</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f5f7; margin: 0; padding: 24px; }
        .card { max-width: 360px; margin: 60px auto; background: #fff; border-radius: 8px; padding: 24px;
                box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        h1 { margin-top: 0; font-size: 22px; }
        label { display: block; font-size: 14px; color: #444; margin: 14px 0 4px; }
        input { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; }
        button { margin-top: 18px; width: 100%; background: #1d5fa8; color: #fff; border: 0; border-radius: 8px;
                 padding: 12px; font-size: 15px; cursor: pointer; }
        .error { background: #fdecec; color: #b91c1c; padding: 10px; border-radius: 6px; font-size: 14px; }
    </style>
</head>
<body>
<div class="card">
    <h1>Monitor de Servidor</h1>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

        <label for="password">Contraseña</label>
        <input id="password" type="password" name="password" required>

        <button type="submit">Ingresar</button>
    </form>
</div>
</body>
</html>
