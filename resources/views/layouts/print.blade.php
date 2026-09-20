<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 antialiased print:bg-white">
        <main class="mx-auto max-w-3xl p-6 print:max-w-none print:p-0">
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>
