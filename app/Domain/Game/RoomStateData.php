<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Enums\RoomState;
use App\Models\Provider;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\TimelineItem;
use Illuminate\Support\Collection;

class RoomStateData
{
    public function __construct(
        private readonly RoomSession $roomSession,
    ) {}

    /**
     * @return array{
     *   room: array<string, mixed>,
     *   me: array<string, mixed>|null,
     *   players: array<int, array<string, mixed>>,
     *   providers: array<int, array{id: int, key: string, name: string}>,
     *   round: array<string, mixed>|null,
     *   timeline: array<int, array<string, mixed>>|null
     * }
     */
    public function forRoom(Room $room): array
    {
        $room->loadMissing([
            'pack',
            'playbackProvider',
            'players',
            'currentRound.track',
            'currentRound.placements.player',
            'currentRound.placements.insertedAfterTrack',
        ]);

        $me = $this->roomSession->currentPlayer($room);

        if (! $me) {
            return [
                'room' => $this->room($room, null),
                'me' => null,
                'players' => [],
                'providers' => $this->providers(),
                'round' => null,
                'timeline' => null,
            ];
        }

        return [
            'room' => $this->room($room, $me),
            'me' => $this->me($room, $me),
            'players' => $this->players($room, $me),
            'providers' => $this->providers(),
            'round' => $this->round($room, $me),
            'timeline' => $this->timeline($me),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function room(Room $room, ?RoomPlayer $me): array
    {
        return [
            'code' => $room->code,
            'state' => $room->state->value,
            'pack' => [
                'name' => $room->pack->name,
                'description' => $room->pack->description,
                'tracks_count' => $room->pack->tracks_count ?? null,
                'spotify_tracks_count' => $room->pack->spotify_tracks_count ?? null,
                'tidal_tracks_count' => $room->pack->tidal_tracks_count ?? null,
            ],
            'playback_provider' => $room->playbackProvider ? [
                'id' => $room->playbackProvider->id,
                'key' => $room->playbackProvider->key,
                'name' => $room->playbackProvider->name,
            ] : null,
            'is_host' => (bool) ($me?->is_host ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function me(Room $room, RoomPlayer $me): array
    {
        return [
            'id' => $me->id,
            'display_name' => $me->display_name,
            'score' => $me->score,
            'is_host' => $me->is_host,
            'has_placed' => $room->currentRound ? $me->hasPlacementForRound($room->currentRound) : false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function players(Room $room, ?RoomPlayer $me): array
    {
        return $room
            ->players
            ->sortBy('joined_at')
            ->values()
            ->map(fn (RoomPlayer $player): array => [
                'id' => $player->id,
                'display_name' => $player->display_name,
                'score' => $player->score,
                'is_host' => $player->is_host,
                'is_me' => (bool) ($me && $player->id === $me->id),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, key: string, name: string}>
     */
    private function providers(): array
    {
        return Provider::query()
            ->whereIn('key', ['spotify', 'tidal'])
            ->where('is_active', true)
            ->get()
            ->map(fn (Provider $provider): array => [
                'id' => $provider->id,
                'key' => $provider->key,
                'name' => $provider->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function round(Room $room, ?RoomPlayer $me): ?array
    {
        if (! $room->currentRound) {
            return null;
        }

        $round = $room->currentRound;

        $base = [
            'id' => $round->id,
            'round_number' => $round->round_number,
            'status' => $round->status->value,
            'started_at' => $round->started_at?->toIso8601String(),
            'placement_unlocks_at' => $round->placement_unlocks_at?->toIso8601String(),
            'revealed_at' => $round->revealed_at?->toIso8601String(),
            'placements_count' => $round->placements()->count(),
            'players_count' => $room->players()->count(),
        ];

        $provider = $room->playbackProvider;
        $track = $round->track;

        if ($provider && $track) {
            $base['playback'] = [
                'provider_key' => $provider->key,
                'provider_name' => $provider->name,
                'preview_url' => $track->preview_url,
                'external_url' => $track->playbackUrlForProvider($provider),
            ];
        } else {
            $base['playback'] = null;
        }

        if ($room->state === RoomState::Reveal || $round->isRevealed()) {
            $base['track'] = [
                'id' => $track?->id,
                'year' => $track?->release_date?->format('Y'),
                'title' => $track?->title,
                'artist' => $track?->artist,
                'artwork_url' => $track?->artwork_url,
            ];

            $base['placements'] = $round
                ->placements
                ->map(fn ($placement): array => [
                    'id' => $placement->id,
                    'player' => [
                        'id' => $placement->player->id,
                        'display_name' => $placement->player->display_name,
                    ],
                    'is_correct' => $placement->is_correct,
                    'points_earned' => $placement->points_earned,
                    'inserted_after_track_id' => $placement->inserted_after_track_id,
                    'inserted_after_year' => $placement->insertedAfterTrack?->release_date?->format('Y'),
                ])
                ->values()
                ->all();
        } else {
            $base['track'] = null;
            $base['placements'] = null;
        }

        return $base;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timeline(RoomPlayer $me): array
    {
        /** @var Collection<int, TimelineItem> $items */
        $items = $me
            ->timelineItems()
            ->with('track')
            ->orderBy('position')
            ->get();

        return $items
            ->map(fn (TimelineItem $item): array => [
                'track_id' => $item->track_id,
                'position' => $item->position,
                'year' => $item->track->release_date->format('Y'),
                'title' => $item->track->title,
                'artist' => $item->track->artist,
                'artwork_url' => $item->track->artwork_url,
            ])
            ->all();
    }
}
