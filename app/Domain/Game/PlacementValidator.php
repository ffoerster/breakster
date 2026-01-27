<?php

declare(strict_types = 1);

namespace App\Domain\Game;

use App\Models\RoomPlayer;
use App\Models\TimelineItem;
use App\Models\Track;
use Illuminate\Support\Collection;

class PlacementValidator
{
    /**
     * Validate if a placement is chronologically correct.
     *
     * Algorithm:
     * - If timeline is empty: always correct
     * - If insertAfterTrackId is null (insert at beginning):
     *   correct if new track's release_date <= first track's release_date
     * - Otherwise:
     *   correct if new track's release_date >= "after" track's release_date
     *   AND (no next track OR new track's release_date <= next track's release_date)
     * - Equal dates are allowed.
     */
    public function isCorrect(RoomPlayer $player, Track $newTrack, ?int $insertAfterTrackId): bool
    {
        $timeline = $this->getPlayerTimeline($player);

        // Empty timeline: always correct
        if ($timeline->isEmpty()) {
            return true;
        }

        $newReleaseDate = $newTrack->release_date;

        // Insert at beginning
        if ($insertAfterTrackId === null) {
            $firstItem = $timeline->first();

            return $newReleaseDate->lte($firstItem->track->release_date);
        }

        // Find the "after" item and its next sibling
        $afterIndex = $timeline->search(static fn(TimelineItem $item) => $item->track_id === $insertAfterTrackId);

        if ($afterIndex === false) {
            // The track they're trying to insert after isn't in their timeline
            // This is invalid, treat as incorrect
            return false;
        }

        $afterItem = $timeline->get($afterIndex);
        $nextItem = $timeline->get($afterIndex + 1);

        // Check: new track must be >= after track's date
        if ($newReleaseDate->lt($afterItem->track->release_date)) {
            return false;
        }

        // Check: if there's a next track, new track must be <= next track's date
        if ($nextItem !== null && $newReleaseDate->gt($nextItem->track->release_date)) {
            return false;
        }

        return true;
    }

    /**
     * Find the correct position (0-indexed) where a track should be inserted
     * in a player's timeline based on release date.
     */
    public function findCorrectPosition(RoomPlayer $player, Track $track): int
    {
        $timeline = $this->getPlayerTimeline($player);

        if ($timeline->isEmpty()) {
            return 0;
        }

        $releaseDate = $track->release_date;

        // Find the first item that has a release date greater than the new track
        foreach ($timeline as $index => $item) {
            if (!($item->track->release_date->gt($releaseDate))) { continue; }

return $index;
        }

        // If we get here, the track goes at the end
        return $timeline->count();
    }

    /**
     * Get the track ID that the new track should be inserted after
     * for a correct placement, or null if it should be at the beginning.
     */
    public function findCorrectInsertAfterTrackId(RoomPlayer $player, Track $track): ?int
    {
        $position = $this->findCorrectPosition($player, $track);

        if ($position === 0) {
            return null;
        }

        $timeline = $this->getPlayerTimeline($player);

        return $timeline->get($position - 1)?->track_id;
    }

    /**
     * Get player's current timeline sorted by position.
     *
     * @return Collection<int, TimelineItem>
     */
    public function getPlayerTimeline(RoomPlayer $player): Collection
    {
        return $player->timelineItems()->with('track')->orderBy('position')->get();
    }
}
