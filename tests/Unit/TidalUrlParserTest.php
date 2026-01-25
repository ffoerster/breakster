<?php

use App\Domain\Providers\Tidal\TidalUrlParser;

describe('TidalUrlParser', function () {
    beforeEach(function () {
        $this->parser = new TidalUrlParser;
    });

    it('parses track URL with browse path', function () {
        $result = $this->parser->parse('https://tidal.com/browse/track/123456');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '123456',
        ]);
    });

    it('parses playlist URL with browse path', function () {
        $result = $this->parser->parse('https://tidal.com/browse/playlist/uuid-1234-5678');

        expect($result)->toBe([
            'type' => 'playlist',
            'id' => 'uuid-1234-5678',
        ]);
    });

    it('parses track URL without browse path', function () {
        $result = $this->parser->parse('https://tidal.com/track/123456');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '123456',
        ]);
    });

    it('parses URL with trailing slash', function () {
        $result = $this->parser->parse('https://tidal.com/browse/track/123456/');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '123456',
        ]);
    });

    it('handles http protocol', function () {
        $result = $this->parser->parse('http://tidal.com/browse/track/123456');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '123456',
        ]);
    });

    it('handles URLs with whitespace', function () {
        $result = $this->parser->parse('  https://tidal.com/browse/track/123456  ');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '123456',
        ]);
    });

    it('strips query strings', function () {
        $result = $this->parser->parse('https://tidal.com/browse/track/123456?foo=bar');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '123456',
        ]);
    });

    it('parses track URL with trailing segments', function () {
        $result = $this->parser->parse('https://tidal.com/track/153133458/u');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '153133458',
        ]);
    });

    it('parses track URL with query string without browse path', function () {
        $result = $this->parser->parse('https://tidal.com/track/153133458?foo=bar');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '153133458',
        ]);
    });

    it('parses typed track input', function () {
        $result = $this->parser->parse('track:123456');

        expect($result)->toBe([
            'type' => 'track',
            'id' => '123456',
        ]);
    });

    it('parses typed playlist input', function () {
        $result = $this->parser->parse('playlist:uuid-1234-5678');

        expect($result)->toBe([
            'type' => 'playlist',
            'id' => 'uuid-1234-5678',
        ]);
    });

    it('throws exception for invalid URL format', function () {
        $this->parser->parse('https://tidal.com/invalid/path');
    })->throws(InvalidArgumentException::class, 'Could not parse TIDAL URL');

    it('throws exception for empty input', function () {
        $this->parser->parse('');
    })->throws(InvalidArgumentException::class, 'Invalid TIDAL input');

    it('throws exception for non-numeric non-URL input', function () {
        $this->parser->parse('not-a-valid-input');
    })->throws(InvalidArgumentException::class, 'Invalid TIDAL input');
});
