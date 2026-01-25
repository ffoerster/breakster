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

    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-float { animation: float 3s ease-in-out infinite; }
        .animate-fade-in-up { animation: fade-in-up 0.6s ease-out forwards; }
        .animate-delay-100 { animation-delay: 100ms; }
        .animate-delay-200 { animation-delay: 200ms; }
        .animate-delay-300 { animation-delay: 300ms; }
        .animate-delay-400 { animation-delay: 400ms; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-50 overflow-x-hidden">
    <!-- Background gradient orbs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-accent-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-pink-500/20 rounded-full blur-3xl"></div>
    </div>

    <div class="relative min-h-screen flex flex-col">
        <!-- Main content -->
        <main class="flex-1 flex flex-col items-center justify-center p-6">
            <!-- Hero section -->
            <div class="text-center mb-12 opacity-0 animate-fade-in-up">
                <!-- Logo/Icon -->
                <div class="mb-6 animate-float">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-linear-to-br from-accent-400 to-accent-600 shadow-lg shadow-accent-500/30">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                        </svg>
                    </div>
                </div>

                <h1 class="text-5xl sm:text-7xl font-black mb-4 bg-clip-text text-transparent bg-linear-to-r from-accent-400 via-accent-500 to-pink-500">
                    Breakster
                </h1>
                <p class="text-xl sm:text-2xl text-gray-600 dark:text-gray-400 max-w-lg mx-auto font-medium">
                    A timeline music game for DnB nerds, DJs, and crate diggers.
                </p>
            </div>

            <!-- Action buttons -->
            <div class="flex flex-col sm:flex-row gap-4 w-full max-w-md mb-16 opacity-0 animate-fade-in-up animate-delay-100">
                <a
                    href="{{ route('room.create') }}"
                    class="btn-interactive flex-1 py-4 px-8 bg-accent-500 hover:bg-accent-600 text-white font-bold rounded-xl text-center text-lg shadow-lg shadow-accent-500/25"
                >
                    Create Room
                </a>
                <a
                    href="{{ route('room.join') }}"
                    class="btn-interactive flex-1 py-4 px-8 bg-white/80 dark:bg-gray-800/80 hover:bg-white dark:hover:bg-gray-700 text-gray-900 dark:text-gray-50 font-bold rounded-xl text-center text-lg shadow-lg backdrop-blur border border-gray-200 dark:border-gray-700"
                >
                    Join Room
                </a>
            </div>

            <!-- How to play -->
            <div class="w-full max-w-3xl opacity-0 animate-fade-in-up animate-delay-200">
                <h2 class="text-center text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-6">How to Play</h2>
                <div class="grid sm:grid-cols-3 gap-4">
                    <div class="bg-white/70 dark:bg-gray-900/70 backdrop-blur rounded-2xl p-6 border border-gray-200 dark:border-gray-800 text-center">
                        <div class="w-12 h-12 rounded-full bg-accent-500/10 flex items-center justify-center mx-auto mb-4">
                            <span class="text-2xl font-bold text-accent-500">1</span>
                        </div>
                        <h3 class="font-bold mb-2">Listen</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Hear a mystery track — no title, artist, or year revealed</p>
                    </div>
                    <div class="bg-white/70 dark:bg-gray-900/70 backdrop-blur rounded-2xl p-6 border border-gray-200 dark:border-gray-800 text-center">
                        <div class="w-12 h-12 rounded-full bg-accent-500/10 flex items-center justify-center mx-auto mb-4">
                            <span class="text-2xl font-bold text-accent-500">2</span>
                        </div>
                        <h3 class="font-bold mb-2">Place</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Drag the track into your timeline in chronological order</p>
                    </div>
                    <div class="bg-white/70 dark:bg-gray-900/70 backdrop-blur rounded-2xl p-6 border border-gray-200 dark:border-gray-800 text-center">
                        <div class="w-12 h-12 rounded-full bg-accent-500/10 flex items-center justify-center mx-auto mb-4">
                            <span class="text-2xl font-bold text-accent-500">3</span>
                        </div>
                        <h3 class="font-bold mb-2">Score</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Earn points for correct placements — faster answers score more!</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-6 text-center text-gray-500 dark:text-gray-600 text-sm opacity-0 animate-fade-in-up animate-delay-300">
            <p>Gather your friends, pick a music pack, and prove who knows their music history best</p>
        </footer>
    </div>
</body>
</html>
