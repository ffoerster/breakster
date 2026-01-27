<?php

declare(strict_types = 1);

namespace App\Domain\Providers\Tidal;

use InvalidArgumentException;

class TidalUrlParser
{
    private const RESOURCE_TYPES = ['track', 'playlist'];

    public function parse(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            throw new InvalidArgumentException(
                'Invalid TIDAL input: (empty). Expected URL or typed ID (track:<id>, playlist:<id>).',
            );
        }

        if (preg_match('/^(?<type>track|playlist):(?<id>[a-zA-Z0-9\-]+)$/i', $input, $matches)) {
            $type = strtolower($matches['type']);
            $id = (string) $matches['id'];

            if ($type === 'track' && !ctype_digit($id)) {
                throw new InvalidArgumentException("Invalid TIDAL input: {$input}. Track IDs must be numeric.");
            }

            return [
                'type' => $type,
                'id' => $id,
            ];
        }

        // Try URL parsing first
        if (str_contains($input, 'tidal.com')) {
            return $this->parseUrl($input);
        }

        throw new InvalidArgumentException(
            "Invalid TIDAL input: {$input}. Expected URL or typed ID (track:<id>, playlist:<id>).",
        );
    }

    private function parseUrl(string $url): array
    {
        // Normalize URL
        $url = str_replace(['http://', 'https://'], 'https://', $url);

        // Strip query string + fragment
        $url = preg_split('/[?#]/', $url, 2)[0] ?? $url;

        $parsed = parse_url($url);
        $path = is_array($parsed) ? $parsed['path'] ?? null : null;

        if (!is_string($path) || $path === '') {
            throw new InvalidArgumentException(
                "Could not parse TIDAL URL: {$url}. Expected format: tidal.com/(browse/){type}/{id}",
            );
        }

        $segments = array_values(array_filter(
            explode('/', $path),
            static fn(string $segment): bool => $segment !== '',
        ));

        if (( $segments[0] ?? null ) === 'browse') {
            array_shift($segments);
        }

        $type = strtolower((string) ( $segments[0] ?? '' ));
        $id = (string) ( $segments[1] ?? '' );

        if ($type === '' || $id === '') {
            throw new InvalidArgumentException(
                "Could not parse TIDAL URL: {$url}. Expected format: tidal.com/(browse/){type}/{id}",
            );
        }

        if (!in_array($type, self::RESOURCE_TYPES, true)) {
            throw new InvalidArgumentException(
                "Could not parse TIDAL URL: {$url}. Expected format: tidal.com/(browse/){type}/{id}",
            );
        }

        if ($type === 'track' && !ctype_digit($id)) {
            throw new InvalidArgumentException("Could not parse TIDAL URL: {$url}. Track IDs must be numeric.");
        }

        return [
            'type' => $type,
            'id' => $id,
        ];
    }
}
