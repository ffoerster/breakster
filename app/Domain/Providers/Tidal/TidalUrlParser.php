<?php

declare(strict_types=1);

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
            return [
                'type' => strtolower($matches['type']),
                'id' => $matches['id'],
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

        // Match patterns like:
        // https://tidal.com/browse/track/12345
        // https://tidal.com/browse/playlist/uuid-1234-5678
        // https://listen.tidal.com/track/12345
        $pattern = '#tidal\.com/(?:browse/)?(?<type>track|playlist)/(?<id>[a-zA-Z0-9\-]+)#i';

        if (preg_match($pattern, $url, $matches)) {
            $type = strtolower($matches['type']);

            if (!in_array($type, self::RESOURCE_TYPES, true)) {
                throw new InvalidArgumentException("Unsupported TIDAL resource type: {$type}");
            }

            return [
                'type' => $type,
                'id' => $matches['id'],
            ];
        }

        throw new InvalidArgumentException(
            "Could not parse TIDAL URL: {$url}. Expected format: tidal.com/browse/{type}/{id}",
        );
    }
}
