<div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-3xl font-bold">Room {{ $room->code }}</h1>
            <div class="mt-2">
                <div class="text-sm text-gray-500 uppercase tracking-wide">Music Pack</div>
                <p class="text-xl font-semibold text-purple-400">{{ $room->pack->name }}</p>
                @if ($room->pack->description)
                    <p class="text-sm text-gray-400 mt-1">{{ $room->pack->description }}</p>
                @endif
            </div>
        </div>
        <div class="text-right">
            <div class="text-sm text-gray-400">Share this code</div>
            <div class="text-2xl font-mono font-bold text-purple-400">{{ $room->code }}</div>
        </div>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4">Players ({{ $room->players->count() }})</h2>
        <div class="grid gap-3">
            @foreach ($room->players()->orderBy('joined_at')->get() as $roomPlayer)
                <div class="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center font-bold">
                            {{ strtoupper(substr($roomPlayer->display_name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="font-medium">
                                {{ $roomPlayer->display_name }}
                                @if ($roomPlayer->id === $player->id)
                                    <span class="text-purple-400">(You)</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if ($roomPlayer->is_host)
                        <span class="px-3 py-1 bg-yellow-500/20 text-yellow-400 text-sm rounded-full">Host</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex gap-4">
        @if ($isHost)
            <button
                wire:click="startRound"
                class="flex-1 py-4 px-6 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition"
                wire:loading.attr="disabled"
                @if ($room->players->count() < 1) disabled @endif
            >
                Start Game
            </button>
        @else
            <div class="flex-1 py-4 px-6 bg-gray-700 text-gray-400 font-bold rounded-lg text-center">
                Waiting for host to start...
            </div>
        @endif

        <button
            wire:click="leaveRoom"
            wire:confirm="Are you sure you want to leave?"
            class="py-4 px-6 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition"
        >
            Leave
        </button>
    </div>
</div>
