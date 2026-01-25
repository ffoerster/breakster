@php
    $round = $room->currentRound;
    $track = $round?->track;
    $timeline = $player->timeline();
    $hasPlaced = $player->hasPlacementForRound($round);

    $placementDelaySeconds = 5;
    $placementUnlockAt = $round?->started_at?->copy()->addSeconds($placementDelaySeconds);
    $isPlacementLocked = $placementUnlockAt !== null && now()->lt($placementUnlockAt);
    $placementUnlockIn = $isPlacementLocked ? now()->diffInSeconds($placementUnlockAt) : 0;
@endphp

<div class="space-y-6">
    {{-- Track Player --}}
    <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8 text-center">
        <div class="text-sm text-gray-400 mb-2">Round {{ $round->round_number }}</div>
        <h2 class="text-2xl font-bold mb-6">Listen and place this track!</h2>

        <div class="max-w-sm mx-auto">
            @if ($track->preview_url)
                <audio controls class="w-full" autoplay>
                    <source src="{{ $track->preview_url }}" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
            @else
                <a
                    href="{{ $track->external_url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 px-6 py-4 bg-blue-600 hover:bg-blue-700 rounded-lg font-bold transition"
                >
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                    </svg>
                    Play in TIDAL
                </a>
            @endif
        </div>

        <p class="mt-4 text-gray-400 text-sm">
            Listen carefully - you need to place this in your timeline!
        </p>
    </div>

    {{-- Timeline & Placement --}}
    <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
        <h3 class="text-xl font-bold mb-4">Your Timeline</h3>

        @if ($isPlacementLocked)
            <div class="p-4 bg-blue-500/20 border border-blue-500 rounded-lg text-center mb-4">
                Placement unlocks in {{ $placementUnlockIn }}s
            </div>
        @endif

        @if ($hasPlaced)
            <div class="p-4 bg-green-500/20 border border-green-500 rounded-lg text-center mb-4">
                You've placed your guess! Waiting for other players...
            </div>
        @endif

        @if ($timeline->isEmpty() && !$hasPlaced)
            <div class="text-center py-8">
                <p class="text-gray-400 mb-4">Your timeline is empty. This will be your first track!</p>
                <button
                    wire:click="submitPlacement(null)"
                    class="px-8 py-4 bg-purple-600 hover:bg-purple-700 rounded-lg font-bold transition disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                    @disabled($isPlacementLocked)
                >
                    Place Here
                </button>
            </div>
        @else
            @if ($timeline->isNotEmpty())
                <div class="mb-6">
                    <div class="text-sm text-gray-400 mb-2">Drag cards to reorder your timeline</div>
                    <div x-data="timelineSortable" class="space-y-2">
                        @foreach ($timeline as $item)
                            <div
                                wire:key="timeline-item-{{ $item->id }}"
                                data-track-id="{{ $item->track_id }}"
                                class="flex items-center gap-4 p-4 bg-gray-700/30 rounded-lg cursor-grab"
                            >
                                @if ($item->track->artwork_url)
                                    <img src="{{ $item->track->artwork_url }}" alt="" class="w-16 h-16 rounded object-cover">
                                @else
                                    <div class="w-16 h-16 rounded bg-gray-600 flex items-center justify-center">
                                        <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/>
                                        </svg>
                                    </div>
                                @endif
                                <div class="flex-1">
                                    <div class="font-medium">{{ $item->track->title }}</div>
                                    <div class="text-sm text-gray-400">{{ $item->track->artist }}</div>
                                    <div class="text-sm text-purple-400">{{ $item->track->release_date->format('Y') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (!$hasPlaced)
                <div class="space-y-3">
                    <div class="text-sm text-gray-400">Or pick a position (fallback)</div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <select
                            wire:model="insertAfterTrackId"
                            class="w-full px-4 py-3 rounded-lg bg-gray-900/60 border border-gray-700"
                            @disabled($isPlacementLocked)
                        >
                            <option value="">At beginning</option>
                            @foreach ($timeline as $item)
                                <option value="{{ $item->track_id }}">
                                    After {{ $item->track->release_date->format('Y') }} — {{ $item->track->title }}
                                </option>
                            @endforeach
                        </select>
                        <button
                            wire:click="submitPlacement"
                            class="px-8 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-bold transition disabled:opacity-50 disabled:cursor-not-allowed"
                            wire:loading.attr="disabled"
                            @disabled($isPlacementLocked)
                        >
                            Submit
                        </button>
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- Host Controls --}}
    @if ($isHost)
        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-4">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-400">
                    {{ $round->placements()->count() }} / {{ $room->players()->count() }} players have placed
                </div>
                <button
                    wire:click="revealRound"
                    class="px-6 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg font-bold transition"
                    wire:loading.attr="disabled"
                >
                    Reveal Answers
                </button>
            </div>
        </div>
    @endif
</div>
