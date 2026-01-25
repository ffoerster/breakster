@php
    $round = $room->currentRound;
    $track = $round?->track;
    $placements = $round?->placements()->with('player')->get() ?? collect();
    $leaderboard = app(\App\Domain\Game\GameService::class)->getLeaderboard($room);
@endphp

<div class="space-y-6">
    {{-- Revealed Track --}}
    <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8 text-center">
        <div class="text-sm text-gray-400 mb-2">Round {{ $round->round_number }} - Revealed!</div>

        <div class="flex items-center justify-center gap-6 mb-6">
            @if ($track->artwork_url)
                <img src="{{ $track->artwork_url }}" alt="" class="w-32 h-32 rounded-lg shadow-lg">
            @endif
            <div class="text-left">
                <div class="text-4xl md:text-5xl font-black text-purple-400 leading-none mb-3">
                    {{ $track->release_date->format('Y') }}
                </div>
                <h2 class="text-2xl font-bold">{{ $track->title }}</h2>
                <p class="text-lg text-gray-300">{{ $track->artist }}</p>
            </div>
        </div>
    </div>

    {{-- Results --}}
    <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
        <h3 class="text-xl font-bold mb-4">Results</h3>
        <div class="space-y-3">
            @foreach ($placements as $placement)
                <div class="flex items-center justify-between p-4 rounded-lg {{ $placement->is_correct ? 'bg-green-500/20 border border-green-500' : 'bg-red-500/20 border border-red-500' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full {{ $placement->is_correct ? 'bg-green-600' : 'bg-red-600' }} flex items-center justify-center">
                            @if ($placement->is_correct)
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            @else
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </div>
                        <div>
                            <div class="font-medium">
                                {{ $placement->player->display_name }}
                                @if ($placement->player->id === $player->id)
                                    <span class="text-gray-400">(You)</span>
                                @endif
                            </div>
                            <div class="text-sm text-gray-400">
                                @if ($placement->isAtBeginning())
                                    Placed at beginning
                                @else
                                    Placed after {{ $placement->insertedAfterTrack?->release_date->format('Y') ?? 'unknown' }}
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        @if ($placement->is_correct)
                            <div class="text-green-400 font-bold">+{{ $placement->points_earned }} pts</div>
                        @else
                            <div class="text-red-400">0 pts</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Leaderboard --}}
    <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
        <h3 class="text-xl font-bold mb-4">Leaderboard</h3>
        <div class="space-y-2">
            @foreach ($leaderboard as $entry)
                <div class="flex items-center justify-between p-3 rounded-lg {{ $entry['player']->id === $player->id ? 'bg-purple-500/20' : 'bg-gray-700/30' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center font-bold text-sm">
                            {{ $entry['rank'] }}
                        </div>
                        <span>{{ $entry['player']->display_name }}</span>
                    </div>
                    <span class="font-bold text-purple-400">{{ $entry['score'] }} pts</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Host Controls --}}
    @if ($isHost)
        <div class="flex justify-center">
            <button
                wire:click="nextRound"
                class="px-8 py-4 bg-green-600 hover:bg-green-700 rounded-lg font-bold text-lg transition"
                wire:loading.attr="disabled"
            >
                Next Round
            </button>
        </div>
    @else
        <div class="text-center text-gray-400">
            Waiting for host to start the next round...
        </div>
    @endif
</div>
