<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Breakster - Timeline Music Game</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 via-purple-900 to-gray-900 text-white">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="text-center mb-12">
            <h1 class="text-6xl font-bold mb-4 bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-400">
                Breakster
            </h1>
            <p class="text-xl text-gray-300 max-w-md mx-auto">
                Listen to tracks and place them in chronological order. Test your music knowledge!
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 w-full max-w-md">
            <a
                href="{{ route('room.create') }}"
                class="flex-1 py-4 px-6 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg text-center transition shadow-lg"
            >
                Create Room
            </a>
            <a
                href="{{ route('room.join') }}"
                class="flex-1 py-4 px-6 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg text-center transition shadow-lg"
            >
                Join Room
            </a>
        </div>

        <div class="mt-16 text-center text-gray-500 text-sm">
            <p>Gather your friends, pick a music pack, and see who knows their music history best!</p>
        </div>
    </div>
</body>
</html>
