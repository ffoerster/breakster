<div class="w-full max-w-4xl" wire:poll.2s>
    @if (session('error'))
        <div class="mb-4 p-4 bg-red-500/20 border border-red-500 rounded-lg text-red-400">
            {{ session('error') }}
        </div>
    @endif

    @if (!$player)
        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8 text-center">
            <h2 class="text-2xl font-bold mb-4">You're not in this room</h2>
            <a href="{{ route('room.join', ['code' => $room->code]) }}" class="inline-block px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-bold transition">
                Join Room {{ $room->code }}
            </a>
        </div>
    @else
        @switch($state)
            @case(\App\Domain\Game\Enums\RoomState::Lobby)
                @include('livewire.room.partials.lobby')
                @break

            @case(\App\Domain\Game\Enums\RoomState::InRound)
                @include('livewire.room.partials.round')
                @break

            @case(\App\Domain\Game\Enums\RoomState::Reveal)
                @include('livewire.room.partials.reveal')
                @break

            @case(\App\Domain\Game\Enums\RoomState::Finished)
                @include('livewire.room.partials.finished')
                @break
        @endswitch
    @endif
</div>
