{{-- Custom 500 page. Laravel 13's default error renderer
     (markdown.blade.php) compila views que pueden no existir en
     storage cuando storage:link no se ha ejecutado o view:clear corre
     justo antes de un 500. Este view custom evita la cadena de
     FileNotFoundException al renderizar el error page. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — {{ __('Server Error') }}</title>
    <style>
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: #fef2f2;
            color: #7f1d1d;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
        }
        h1 {
            font-size: 4rem;
            margin: 0 0 1rem;
        }
        pre {
            background: #fee2e2;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: left;
            font-size: 0.875rem;
            overflow-x: auto;
            color: #1e293b;
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
        <h1>500</h1>
        <p>{{ __('Something went wrong on our end.') }}</p>
        <p><a href="{{ url('/') }}">{{ __('Go to homepage') }}</a></p>
    </div>
</body>
</html>
