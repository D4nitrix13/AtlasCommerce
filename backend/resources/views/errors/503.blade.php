{{-- Custom 503 page para storefront en mantenimiento (RF-STOR-132).
     Offline-safe: NO lee de BD ni de configuración dinámica — usa
     el nombre de tienda pasado por el middleware. Si el propio backend
     está caído, este template sigue renderizando porque Laravel cachea
     los Blade views compilados. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 — {{ $storeName ?? 'Tienda' }}</title>
    <style>
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 480px;
        }
        h1 {
            font-size: 4rem;
            margin: 0 0 0.5rem;
            color: #64748b;
        }
        h2 {
            font-size: 1.5rem;
            margin: 0 0 1rem;
            color: #1e293b;
            font-weight: 600;
        }
        p {
            margin: 0.75rem 0;
            line-height: 1.5;
        }
        .hint {
            color: #64748b;
            font-size: 0.875rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>503</h1>
        <h2>{{ $storeName ?? 'Tienda' }}</h2>
        <p>Estamos trabajando para mejorar tu experiencia.</p>
        <p>Volvemos pronto. Gracias por tu paciencia.</p>
        <p class="hint">Si necesitás ayuda, contactanos directamente.</p>
    </div>
</body>
</html>
