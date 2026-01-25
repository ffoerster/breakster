<?php

declare(strict_types=1);

namespace App\Livewire\Room;

use App\Domain\Game\Enums\RoomState;
use App\Domain\Game\GameService;
use App\Events\RoomStateChanged;
use App\Models\Provider;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    private const int PLACEMENT_DELAY_SECONDS = 5;

    public Room $room;

    public ?int $insertAfterTrackId = null;

    public function mount(string $code): void
    {
        $this->room = Room::with([
            'pack' => fn($query) => $query->withCount(['spotifyTracks', 'tidalTracks', 'tracks']),
        ])->where('code', strtoupper($code))->firstOrFail();
    }

    #[Computed]
    public function currentPlayer(): ?RoomPlayer
    {
        $playerId = session()->get('room_player_id');

        if ($playerId) {
            $player = RoomPlayer::find($playerId);
            if ($player && $player->room_id === $this->room->id) {
                return $player;
            }
        }

        if (auth()->check()) {
            return $this->room
                ->players()
                ->where('user_id', auth()->id())
                ->first();
        }

        return null;
    }

    #[Computed]
    public function isHost(): bool
    {
        return $this->currentPlayer?->is_host ?? false;
    }

    /**
     * @return Collection<int, Provider>
     */
    #[Computed]
    public function playbackProviders(): Collection
    {
        return Provider::query()
            ->whereIn('key', ['spotify', 'tidal'])
            ->where('is_active', true)
            ->get();
    }

    public function setPlaybackProvider(int $providerId): void
    {
        if (!$this->isHost) {
            abort(403, 'Only the host can select the playback provider.');
        }

        if ($this->room->state !== RoomState::Lobby) {
            return;
        }

        $provider = Provider::query()
            ->whereIn('key', ['spotify', 'tidal'])
            ->where('is_active', true)
            ->findOrFail($providerId);

        $this->room->update(['playback_provider_id' => $provider->id]);
        $this->room->refresh();

        event(new RoomStateChanged($this->room->code, $this->room->state, $this->room->current_round_id));
    }

    public function startRound(GameService $gameService): void
    {
        if (!$this->isHost) {
            return;
        }

        try {
            $gameService->startRound($this->room);
        } catch (\RuntimeException|\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function submitPlacement(GameService $gameService, ?int $insertAfterTrackId = null): void
    {
        $player = $this->currentPlayer;
        if (!$player || !$this->room->currentRound) {
            return;
        }

        $insertAfterTrackId = $insertAfterTrackId ?? $this->insertAfterTrackId;

        $key = sprintf('placement-submit:%d', $player->id);
        if (RateLimiter::tooManyAttempts($key, 30)) {
            session()->flash('error', 'Too many placement attempts. Please slow down.');

            return;
        }

        RateLimiter::hit($key, 60);

        $placementUnlocksAt = $this->room->currentRound->placement_unlocks_at;
        if ($placementUnlocksAt !== null && now()->lt($placementUnlocksAt)) {
            session()->flash('error', 'Placement will unlock in a moment.');

            return;
        }

        try {
            $gameService->submitPlacement($player, $this->room->currentRound, $insertAfterTrackId);

            // Dispatch event to update UI state
            $this->dispatch('placement-submitted');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function revealRound(GameService $gameService): void
    {
        if (!$this->isHost || !$this->room->currentRound) {
            return;
        }

        try {
            $gameService->revealRound($this->room->currentRound);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function nextRound(GameService $gameService): void
    {
        if (!$this->isHost) {
            return;
        }

        try {
            $gameService->nextRound($this->room);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function leaveRoom(GameService $gameService): void
    {
        $player = $this->currentPlayer;
        if (!$player) {
            return;
        }

        $gameService->leaveRoom($player);
        session()->forget('room_player_id');

        $this->redirect(route('home'), navigate: true);
    }

    /**
     * @param  array<int, int|string>  $orderedTrackIds
     */
    public function reorderTimeline(GameService $gameService, array $orderedTrackIds): void
    {
        $player = $this->currentPlayer;
        if (!$player) {
            return;
        }

        try {
            $gameService->reorderTimeline($player, $orderedTrackIds);

            // Refresh timeline and dispatch event for JS to re-init Sortable
            $player->refresh();
            $this->dispatch('timeline:updated');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    #[Computed]
    public function title(): string
    {
        return "Room {$this->room->code} - Breakster";
    }

    /**
     * Handle real-time event: players updated (join/leave)
     */
    #[On('roomPlayersUpdated')]
    public function onRoomPlayersUpdated(): void
    {
        $this->refreshRoom();
        unset($this->currentPlayer);
    }

    /**
     * Handle real-time event: round started
     */
    #[On('roundStarted')]
    public function onRoundStarted(): void
    {
        $this->refreshRoom();
    }

    /**
     * Handle real-time event: placement submitted
     */
    #[On('placementSubmitted')]
    public function onPlacementSubmitted(): void
    {
        $this->refreshRound();
    }

    /**
     * Handle real-time event: round revealed
     */
    #[On('roundRevealed')]
    public function onRoundRevealed(): void
    {
        $this->refreshRoom();
    }

    /**
     * Handle real-time event: room state changed
     */
    #[On('roomStateChanged')]
    public function onRoomStateChanged(): void
    {
        $this->refreshRoom();
    }

    /**
     * Refresh room with relationships
     */
    private function refreshRoom(): void
    {
        $this->room->load(['pack', 'players', 'currentRound', 'playbackProvider']);
        $this->room->refresh();
    }

    /**
     * Refresh only round data
     */
    private function refreshRound(): void
    {
        if ($this->room->currentRound) {
            $this->room->currentRound->refresh();
            $this->room->load('currentRound.placements');
        }
    }

    public function render(): View
    {
        $this->refreshRoom();

        return view('livewire.room.show', [
            'state' => $this->room->state,
            'player' => $this->currentPlayer,
            'isHost' => $this->isHost,
        ])->title($this->title);
    }
}
