<?php

declare(strict_types = 1);

namespace App\Console\Commands;

use App\Domain\Game\Enums\RoomState;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PruneRoomsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:prune-rooms
        {--dry-run : Show what would be deleted without deleting}
        {--finished-hours=24 : Delete finished rooms older than this many hours}
        {--stalled-hours=2 : Delete stalled in-progress rooms older than this many hours}
        {--empty-hours=1 : Delete empty rooms (no players) older than this many hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old finished, stalled, and empty game rooms';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $finishedHours = $this->parsePositiveIntOption('finished-hours');
        $stalledHours = $this->parsePositiveIntOption('stalled-hours');
        $emptyHours = $this->parsePositiveIntOption('empty-hours');

        if ($finishedHours === null || $stalledHours === null || $emptyHours === null) {
            return self::FAILURE;
        }

        $now = Carbon::now();

        $finishedCutoff = $now->copy()->subHours($finishedHours);
        $stalledCutoff = $now->copy()->subHours($stalledHours);
        $emptyCutoff = $now->copy()->subHours($emptyHours);

        if ($dryRun) {
            $this->info('Dry run: no rooms will be deleted.');
        }

        $deletedFinished = $this->pruneRooms(
            label: "finished > {$finishedHours}h",
            query: Room::query()->where('state', RoomState::Finished)->where('updated_at', '<', $finishedCutoff),
            dryRun: $dryRun,
        );

        $deletedStalled = $this->pruneRooms(
            label: "stalled > {$stalledHours}h",
            query: Room::query()
                ->whereIn('state', [RoomState::InRound, RoomState::Reveal])
                ->whereHas('currentRound', static function (Builder $query) use ($stalledCutoff): void {
                    $query->whereNotNull('started_at')->where('started_at', '<', $stalledCutoff);
                }),
            dryRun: $dryRun,
        );

        $deletedEmpty = $this->pruneRooms(
            label: "empty > {$emptyHours}h",
            query: Room::query()
                ->whereIn('state', [RoomState::Lobby, RoomState::InRound, RoomState::Reveal])
                ->whereDoesntHave('players')
                ->where('created_at', '<', $emptyCutoff),
            dryRun: $dryRun,
        );

        $total = $deletedFinished + $deletedStalled + $deletedEmpty;

        $this->newLine();
        $this->info('Total rooms '.( $dryRun ? 'matched' : 'deleted' ).": {$total}");

        return self::SUCCESS;
    }

    private function parsePositiveIntOption(string $name): ?int
    {
        $value = $this->option($name);

        if (!is_string($value) && !is_int($value)) {
            $this->error("Invalid value for --{$name}.");

            return null;
        }

        $intValue = (int) $value;

        if ((string) $intValue !== (string) $value && !is_int($value)) {
            $this->error("--{$name} must be an integer.");

            return null;
        }

        if ($intValue <= 0) {
            $this->error("--{$name} must be greater than 0.");

            return null;
        }

        return $intValue;
    }

    private function pruneRooms(string $label, Builder $query, bool $dryRun): int
    {
        $count = (int) $query->count();

        if ($count === 0) {
            $this->line("{$label}: 0");

            return 0;
        }

        if ($dryRun) {
            $this->line("{$label}: {$count}");

            return $count;
        }

        $deleted = (int) $query->delete();

        $this->line("{$label}: {$deleted}");

        return $deleted;
    }
}
