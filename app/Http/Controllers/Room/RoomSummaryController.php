<?php

declare(strict_types=1);

namespace App\Http\Controllers\Room;

use App\Domain\Game\Enums\RoomState;
use App\Domain\Game\GameSummaryService;
use App\Domain\Game\RoomSession;
use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;

class RoomSummaryController extends Controller
{
    public function __invoke(
        string $code,
        RoomSession $roomSession,
        GameSummaryService $summaryService,
    ): JsonResponse {
        $room = Room::query()
            ->where('code', strtoupper($code))
            ->firstOrFail();

        $player = $roomSession->requirePlayer($room);

        if ($room->state !== RoomState::Finished) {
            abort(409, 'Game has not finished yet.');
        }

        return response()->json($summaryService->getSummary($room, $player));
    }
}
