{{-- Self-contained on purpose: error pages must render even when the database or build assets are unavailable. --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · REFRA</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; box-sizing: border-box; background: #faf8f5; color: #0f1113; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; text-align: center; }
        main { max-width: 440px; }
        .code { margin: 0; font-size: 12px; letter-spacing: .2em; text-transform: uppercase; color: #8a6f52; }
        h1 { margin: 12px 0 8px; font-size: 28px; line-height: 1.2; }
        p { margin: 0; line-height: 1.6; color: #4a4d50; }
        a { display: inline-block; margin-top: 24px; padding: 12px 24px; border-radius: 8px; background: #0f1113; color: #fff; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
    <main>
        <p class="code">Error @yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a href="{{ url('/') }}">Back to the store</a>
    </main>
</body>
</html>
