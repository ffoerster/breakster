<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Breakster - Timeline Music Game</title>

    <!-- Theme initialization script -->
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'system';
            const isDark = theme === 'dark' ||
                (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-50">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="text-center mb-12">
            <h1 class="text-6xl font-bold mb-4 bg-clip-text text-transparent bg-gradient-to-r from-accent-400 to-accent-600">
                Breakster
            </h1>
            <p class="text-xl text-gray-600 dark:text-gray-400 max-w-md mx-auto">
                Listen to tracks and place them in chronological order. Test your music knowledge!
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 w-full max-w-md">
            <a
                href="{{ route('room.create') }}"
                class="flex-1 py-4 px-6 bg-accent-500 hover:bg-accent-600 text-white font-bold rounded-lg text-center transition shadow-lg"
            >
                Create Room
            </a>
            <a
                href="{{ route('room.join') }}"
                class="flex-1 py-4 px-6 bg-gray-200 hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-900 dark:text-gray-50 font-bold rounded-lg text-center transition shadow-lg"
            >
                Join Room
            </a>
        </div>

        <div class="mt-16 text-center text-gray-500 dark:text-gray-600 text-sm">
            <p>Gather your friends, pick a music pack, and see who knows their music history best!</p>
        </div>
    </div>
</body>
</html>
