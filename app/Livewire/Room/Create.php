<?php

declare(strict_types=1);

namespace App\Livewire\Room;

use App\Domain\Game\GameService;
use App\Models\Pack;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create Room - Breakster')]
class Create extends Component
{
    public ?int $packId = null;

    public string $displayName = '';

    public function mount(): void
    {
        if (auth()->check()) {
            $this->displayName = auth()->user()->name;
        }
    }

    public function rules(): array
    {
        return [
            'packId' => ['required', 'exists:packs,id'],
            'displayName' => ['required', 'string', 'min:2', 'max:30'],
        ];
    }

    public function create(GameService $gameService): void
    {
        $this->validate();

        $pack = Pack::findOrFail($this->packId);

        $room = $gameService->createRoom(pack: $pack, host: auth()->user(), guestDisplayName: $this->displayName);

        $player = $room->players()->first();
        session()->put('room_player_id', $player->id);

        $this->redirect(route('room.show', $room->code), navigate: true);
    }

    #[Computed]
    public function packs(): Collection
    {
        return Pack::query()
            ->where('is_active', true)
            ->whereHas('tracks', function ($query): void {
                $query->where('is_active', true);
            })
            ->withCount([
                'tracks as tracks_count' => function ($query): void {
                    $query->where('is_active', true);
                },
            ])
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.room.create');
    }
}
