<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        <link rel="icon" href="/favicon.png" type="image/png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Public runtime settings only; confidential keys remain server-side. -->
        <script>
            window.EDTSRealtime = {{ Illuminate\Support\Js::from([
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => parse_url(config('app.url'), PHP_URL_HOST),
                'scheme' => parse_url(config('app.url'), PHP_URL_SCHEME),
                'port' => parse_url(config('app.url'), PHP_URL_PORT) ?: (str_starts_with(config('app.url'), 'https://') ? 443 : 80),
            ]) }};
        </script>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
