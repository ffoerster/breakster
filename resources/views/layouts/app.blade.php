<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Breakster' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-full bg-gradient-to-br from-gray-900 via-purple-900 to-gray-900 text-white">
        <div class="min-h-screen flex flex-col">
            <header class="py-4 px-6">
                <a href="{{ route('home') }}" class="text-2xl font-bold text-purple-400 hover:text-purple-300 transition">
                    Breakster
                </a>
            </header>

            <main class="flex-1 flex items-center justify-center px-4 py-8">
                {{ $slot }}
            </main>

            <footer class="py-4 text-center text-gray-500 text-sm">
                A timeline music game
            </footer>
        </div>

        @livewireScripts
    </body>
</html>
