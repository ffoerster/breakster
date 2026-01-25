<?php

declare(strict_types=1);

namespace App\Domain\Providers\Spotify;

use InvalidArgumentException;

class SpotifyUrlParser
{
    private const RESOURCE_TYPES = ['track', 'playlist'];

    /**
     * @return array{type: string, id: string}
     */
    public function parse(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            throw new InvalidArgumentException(
                'Invalid Spotify input: (empty). Expected URL, URI, or typed ID (track:<id>, playlist:<id>).',
            );
        }

        // Handle Spotify URI format: spotify:track:xxx or spotify:playlist:xxx
        if (preg_match('/^spotify:(?<type>track|playlist):(?<id>[a-zA-Z0-9]+)$/i', $input, $matches)) {
            return [
                'type' => strtolower($matches['type']),
                'id' => $matches['id'],
            ];
        }

        // Handle typed ID format: track:xxx or playlist:xxx
        if (preg_match('/^(?<type>track|playlist):(?<id>[a-zA-Z0-9]+)$/i', $input, $matches)) {
            return [
                'type' => strtolower($matches['type']),
                'id' => $matches['id'],
            ];
        }

        // Handle URL format
        if (str_contains($input, 'spotify.com')) {
            return $this->parseUrl($input);
        }

        throw new InvalidArgumentException(
            "Invalid Spotify input: {$input}. Expected URL, URI, or typed ID (track:<id>, playlist:<id>).",
        );
    }

    /**
     * @return array{type: string, id: string}
     */
    private function parseUrl(string $url): array
    {
        // Normalize URL
        $url = str_replace(['http://', 'https://'], 'https://', $url);

        // Strip query string and fragment (removes ?si=xxx tracking params)
        $url = preg_split('/[?#]/', $url, 2)[0] ?? $url;

        $parsed = parse_url($url);
        $path = is_array($parsed) ? ($parsed['path'] ?? null) : null;

        if (! is_string($path) || $path === '') {
            throw new InvalidArgumentException(
                "Could not parse Spotify URL: {$url}. Expected format: open.spotify.com/{type}/{id}",
            );
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));

        // Handle intl-xx paths like /intl-de/track/xxx
        if (str_starts_with((string) ($segments[0] ?? ''), 'intl-')) {
            array_shift($segments);
        }

        $type = strtolower((string) ($segments[0] ?? ''));
        $id = (string) ($segments[1] ?? '');

        if ($type === '' || $id === '') {
            throw new InvalidArgumentException(
                "Could not parse Spotify URL: {$url}. Expected format: open.spotify.com/{type}/{id}",
            );
        }

        if (! in_array($type, self::RESOURCE_TYPES, true)) {
            throw new InvalidArgumentException(
                "Could not parse Spotify URL: {$url}. Unsupported resource type: {$type}. Supported types: track, playlist.",
            );
        }

        // Spotify IDs are 22-character base62 strings
        if (! preg_match('/^[a-zA-Z0-9]+$/', $id)) {
            throw new InvalidArgumentException(
                "Could not parse Spotify URL: {$url}. Invalid Spotify ID format.",
            );
        }

        return [
            'type' => $type,
            'id' => $id,
        ];
    }
}
