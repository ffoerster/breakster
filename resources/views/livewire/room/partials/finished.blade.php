@php
    $leaderboard = app(\App\Domain\Game\GameService::class)->getLeaderboard($room);
    $winner = $leaderboard->first();
@endphp

<div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
    <div class="text-center mb-8">
        <h1 class="text-4xl font-bold mb-4">Game Over!</h1>

        @if ($winner)
            <div class="inline-block p-6 bg-yellow-500/20 rounded-2xl border border-yellow-500 mb-4">
                <div class="text-yellow-400 text-sm mb-2">Winner</div>
                <div class="text-3xl font-bold">{{ $winner['player']->display_name }}</div>
                <div class="text-2xl text-yellow-400">{{ $winner['score'] }} points</div>
            </div>
        @endif
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4 text-center">Final Standings</h2>
        <div class="max-w-md mx-auto space-y-2">
            @foreach ($leaderboard as $entry)
                <div class="flex items-center justify-between p-4 rounded-lg {{ $entry['rank'] === 1 ? 'bg-yellow-500/20 border border-yellow-500' : ($entry['player']->id === $player->id ? 'bg-purple-500/20' : 'bg-gray-700/30') }}">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold {{ $entry['rank'] === 1 ? 'bg-yellow-500 text-black' : 'bg-gray-600' }}">
                            @if ($entry['rank'] === 1)
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z" clip-rule="evenodd"/>
                                </svg>
                            @else
                                {{ $entry['rank'] }}
                            @endif
                        </div>
                        <span class="font-medium">
                            {{ $entry['player']->display_name }}
                            @if ($entry['player']->id === $player->id)
                                <span class="text-gray-400">(You)</span>
                            @endif
                        </span>
                    </div>
                    <span class="font-bold text-purple-400">{{ $entry['score'] }} pts</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex justify-center gap-4">
        <a
            href="{{ route('room.create') }}"
            class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-bold transition"
        >
            Play Again
        </a>
        <a
            href="{{ route('home') }}"
            class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-bold transition"
        >
            Home
        </a>
    </div>
</div>
