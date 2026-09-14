{{-- Custom 403 page. Laravel 13 no incluye 403.blade.php en
     vendor/laravel/framework/.../Exceptions/views/, solo los errores
     HTTP 401/419/429/503. Sin este view, AuthenticationException
     falla con FileNotFoundException al intentar compilar errors::403. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — {{ __('Forbidden') }}</title>
    <style>
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: #fef3c7;
            color: #78350f;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 4rem;
            margin: 0 0 1rem;
        }
        a {
            color: #92400e;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <p>{{ __('You are not authorized to access this resource.') }}</p>
        <p><a href="{{ url('/') }}">{{ __('Go to homepage') }}</a></p>
    </div>
</body>
</html>
