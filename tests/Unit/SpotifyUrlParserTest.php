<?php

use App\Domain\Providers\Spotify\SpotifyUrlParser;

describe('SpotifyUrlParser', function () {
    beforeEach(function () {
        $this->parser = new SpotifyUrlParser;
    });

    it('parses track URL', function () {
        $result = $this->parser->parse('https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
        ]);
    });

    it('parses playlist URL', function () {
        $result = $this->parser->parse('https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M');

        expect($result)->toBe([
            'type' => 'playlist',
            'id' => '37i9dQZF1DXcBWIGoYBM5M',
        ]);
    });

    it('strips query params like si tracking', function () {
        $result = $this->parser->parse('https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh?si=abc123xyz');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
        ]);
    });

    it('strips fragment from URL', function () {
        $result = $this->parser->parse('https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh#section');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
        ]);
    });

    it('parses Spotify URI format for track', function () {
        $result = $this->parser->parse('spotify:track:4iV5W9uYEdYUVa79Axb7Rh');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
        ]);
    });

    it('parses Spotify URI format for playlist', function () {
        $result = $this->parser->parse('spotify:playlist:37i9dQZF1DXcBWIGoYBM5M');

        expect($result)->toBe([
            'type' => 'playlist',
            'id' => '37i9dQZF1DXcBWIGoYBM5M',
        ]);
    });

    it('parses typed ID format for track', function () {
        $result = $this->parser->parse('track:4iV5W9uYEdYUVa79Axb7Rh');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
        ]);
    });

    it('parses typed ID format for playlist', function () {
        $result = $this->parser->parse('playlist:37i9dQZF1DXcBWIGoYBM5M');

        expect($result)->toBe([
            'type' => 'playlist',
            'id' => '37i9dQZF1DXcBWIGoYBM5M',
        ]);
    });

    it('handles http URLs by normalizing to https', function () {
        $result = $this->parser->parse('http://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
        ]);
    });

    it('handles intl paths in URL', function () {
        $result = $this->parser->parse('https://open.spotify.com/intl-de/track/4iV5W9uYEdYUVa79Axb7Rh');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
        ]);
    });

    it('trims whitespace from input', function () {
        $result = $this->parser->parse('  https://open.spotify.com/track/4iV5W9uYEdYUVa79Axb7Rh  ');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
        ]);
    });

    it('throws exception for empty input', function () {
        $this->parser->parse('');
    })->throws(InvalidArgumentException::class, 'Invalid Spotify input: (empty)');

    it('throws exception for whitespace-only input', function () {
        $this->parser->parse('   ');
    })->throws(InvalidArgumentException::class, 'Invalid Spotify input: (empty)');

    it('throws exception for unsupported resource type', function () {
        $this->parser->parse('https://open.spotify.com/artist/0OdUWJ0sBjDrqHygGUXeCF');
    })->throws(InvalidArgumentException::class, 'Unsupported resource type');

    it('throws exception for invalid URL format', function () {
        $this->parser->parse('https://open.spotify.com/');
    })->throws(InvalidArgumentException::class);

    it('throws exception for non-spotify URL', function () {
        $this->parser->parse('https://tidal.com/track/123456');
    })->throws(InvalidArgumentException::class, 'Invalid Spotify input');

    it('throws exception for invalid input format', function () {
        $this->parser->parse('random-invalid-string');
    })->throws(InvalidArgumentException::class, 'Invalid Spotify input');

    it('is case insensitive for type in URI', function () {
        $result = $this->parser->parse('spotify:TRACK:4iV5W9uYEdYUVa79Axb7Rh');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '4iV5W9uYEdYUVa79Axb7Rh',
        ]);
    });

    it('is case insensitive for type in typed ID', function () {
        $result = $this->parser->parse('PLAYLIST:37i9dQZF1DXcBWIGoYBM5M');

        expect($result)->toBe([
            'type' => 'playlist',
            'id' => '37i9dQZF1DXcBWIGoYBM5M',
        ]);
    });
});
