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
                @if (isset($room->pack->tracks_count))
                    <p class="text-sm text-gray-400 mt-1">
                        {{ $room->pack->tracks_count }} total tracks
                        @if ($room->pack->spotify_tracks_count > 0 || $room->pack->tidal_tracks_count > 0)
                            <span class="mx-1">•</span>
                            @if ($room->pack->spotify_tracks_count > 0)
                                <span>{{ $room->pack->spotify_tracks_count }} Spotify</span>
                            @endif
                            @if ($room->pack->spotify_tracks_count > 0 && $room->pack->tidal_tracks_count > 0)
                                <span class="mx-1">•</span>
                            @endif
                            @if ($room->pack->tidal_tracks_count > 0)
                                <span>{{ $room->pack->tidal_tracks_count }} Tidal</span>
                            @endif
                        @endif
                    </p>
                @endif
            </div>
        </div>
        <div class="text-right">
            <div class="text-sm text-gray-400">Share this code</div>
            <div class="text-2xl font-mono font-bold text-purple-400">{{ $room->code }}</div>
        </div>
    </div>

    {{-- Playback Provider Selection --}}
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4">Listening App</h2>
        <p class="text-gray-400 text-sm mb-4">Choose how players will listen to tracks during the game.</p>

        @if ($room->playback_provider_id)
            <div class="flex items-center gap-2 text-green-400 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="font-medium">Listening via {{ $room->playbackProvider->name }}</span>
            </div>
        @else
            <div class="flex items-center gap-2 text-yellow-400 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 animate-pulse" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                </svg>
                <span class="font-medium">Waiting for host to choose listening app...</span>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4">
            @foreach ($this->playbackProviders as $provider)
                @php
                    $isSelected = $room->playback_provider_id === $provider->id;
                @endphp
                <button
                    wire:click="setPlaybackProvider({{ $provider->id }})"
                    @if (!$isHost || $isSelected) disabled @endif
                    class="relative p-4 rounded-lg border-2 transition-all
                        {{ $isSelected
                            ? 'border-purple-500 bg-purple-500/20'
                            : ($isHost ? 'border-gray-600 bg-gray-700/30 hover:border-gray-500 hover:bg-gray-700/50' : 'border-gray-700 bg-gray-800/30 opacity-60 cursor-not-allowed') }}"
                >
                    @if ($isSelected)
                        <div class="absolute top-2 right-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    @endif
                    <div class="text-lg font-bold {{ $isSelected ? 'text-purple-400' : 'text-white' }}">
                        {{ $provider->name }}
                    </div>
                    <div class="text-sm text-gray-400 mt-1">
                        Players open tracks in {{ $provider->name }}
                    </div>
                </button>
            @endforeach
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
            @php
                $canStart = $room->playback_provider_id && $room->players->count() >= 1;
            @endphp
            <div class="flex-1">
                <button
                    wire:click="startRound"
                    class="w-full py-4 px-6 font-bold rounded-lg transition
                        {{ $canStart
                            ? 'bg-green-600 hover:bg-green-700 text-white'
                            : 'bg-gray-600 text-gray-400 cursor-not-allowed' }}"
                    wire:loading.attr="disabled"
                    @if (!$canStart) disabled @endif
                >
                    Start Game
                </button>
                @if (!$room->playback_provider_id)
                    <p class="text-sm text-yellow-400 mt-2 text-center">Select a listening app first</p>
                @endif
            </div>
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
