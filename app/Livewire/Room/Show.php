<?php

declare(strict_types=1);

namespace App\Livewire\Room;

use App\Domain\Game\Enums\RoomState;
use App\Domain\Game\GameService;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Room $room;

    public function mount(string $code): void
    {
        $this->room = Room::where('code', strtoupper($code))->firstOrFail();
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
            return $this->room->players()
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

    public function startRound(GameService $gameService): void
    {
        if (! $this->isHost) {
            return;
        }

        try {
            $gameService->startRound($this->room);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function submitPlacement(GameService $gameService, ?int $insertAfterTrackId = null): void
    {
        $player = $this->currentPlayer;
        if (! $player || ! $this->room->currentRound) {
            return;
        }

        try {
            $gameService->submitPlacement($player, $this->room->currentRound, $insertAfterTrackId);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function revealRound(GameService $gameService): void
    {
        if (! $this->isHost || ! $this->room->currentRound) {
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
        if (! $this->isHost) {
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
        if (! $player) {
            return;
        }

        $gameService->leaveRoom($player);
        session()->forget('room_player_id');

        $this->redirect(route('home'), navigate: true);
    }

    public function getTitle(): string
    {
        return "Room {$this->room->code} - Breakster";
    }

    public function render(): View
    {
        $this->room->refresh();

        return view('livewire.room.show', [
            'state' => $this->room->state,
            'player' => $this->currentPlayer,
            'isHost' => $this->isHost,
        ])->title($this->getTitle());
    }
}
