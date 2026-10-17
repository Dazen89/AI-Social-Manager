<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'AI Social Manager'))</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                * { box-sizing: border-box; }
                body { min-height: 100vh; margin: 0; background: #020617; color: #f8fafc; font-family: system-ui, sans-serif; }
                main { display: grid; min-height: 100vh; place-items: center; padding: 3rem 1.5rem; }
                main > section { width: 100%; max-width: 28rem; padding: 2rem; border: 1px solid #1e293b; border-radius: 1.5rem; background: #0f172a; }
                h1 { margin: 1rem 0 .75rem; font-size: 2rem; }
                p { color: #94a3b8; line-height: 1.6; }
                form { display: grid; gap: 1.25rem; }
                label { display: block; margin-bottom: .5rem; color: #cbd5e1; font-size: .875rem; }
                input[type='email'], input[type='password'], input[type='text'], input[type='url'], select { width: 100%; padding: .8rem 1rem; border: 1px solid #334155; border-radius: .75rem; background: #020617; color: #f8fafc; }
                button { padding: .75rem 1rem; border: 0; border-radius: .75rem; background: #38bdf8; color: #082f49; font-weight: 700; cursor: pointer; }
                a { color: #38bdf8; }
                header { padding: 1rem 1.5rem; border-bottom: 1px solid #1e293b; background: #0f172a; }
                header > div { display: flex; max-width: 72rem; margin: auto; align-items: center; justify-content: space-between; gap: 1.5rem; }
                header form { display: block; }
                header + main { display: block; min-height: auto; max-width: 72rem; margin: auto; }
                header + main > section { max-width: none; }
            </style>
        @endif
    </head>
    <body>
        @yield('content')
    </body>
</html>
