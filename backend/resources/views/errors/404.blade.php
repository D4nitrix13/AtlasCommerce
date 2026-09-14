{{-- Custom 404 page. Laravel 13 no incluye 404.blade.php en
     vendor/laravel/framework/.../Exceptions/views/, solo los errores
     HTTP 401/419/429/503. Sin este view, NotFoundHttpException
     falla con FileNotFoundException al intentar compilar errors::404.
     Ver fix en PR #38 (commit 050b6c0) y este issue tracking. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — {{ __('Not Found') }}</title>
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
        }
        h1 {
            font-size: 4rem;
            margin: 0 0 1rem;
            color: #64748b;
        }
        p {
            margin: 0.5rem 0;
        }
        a {
            color: #0ea5e9;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <p>{{ __('The page you are looking for could not be found.') }}</p>
        <p><a href="{{ url('/') }}">{{ __('Go to homepage') }}</a></p>
    </div>
</body>
</html>
