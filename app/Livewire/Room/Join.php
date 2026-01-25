<?php

declare(strict_types=1);

namespace App\Livewire\Room;

use App\Domain\Game\GameService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Join Room - Breakster')]
class Join extends Component
{
    public string $code = '';

    public string $displayName = '';

    public function mount(?string $code = null): void
    {
        if ($code) {
            $this->code = strtoupper($code);
        }

        if (auth()->check()) {
            $this->displayName = auth()->user()->name;
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:6', 'max:8'],
            'displayName' => ['required', 'string', 'min:2', 'max:30'],
        ];
    }

    public function join(GameService $gameService): void
    {
        $this->validate();

        try {
            $player = $gameService->joinRoom(
                code: strtoupper($this->code),
                user: auth()->user(),
                guestDisplayName: $this->displayName,
            );

            session()->put('room_player_id', $player->id);

            $this->redirect(route('room.show', $player->room->code), navigate: true);
        } catch (\RuntimeException $e) {
            $this->addError('code', $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.room.join');
    }
}
