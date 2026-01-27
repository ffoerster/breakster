<?php

use App\Domain\Game\RoomCodeGenerator;

describe('RoomCodeGenerator', function () {
    it('generates a 6 character code', function () {
        $generator = new RoomCodeGenerator;

        $code = $generator->generate();

        expect($code)->toHaveLength(6);
    });

    it('generates uppercase alphanumeric codes', function () {
        $generator = new RoomCodeGenerator;

        $code = $generator->generate();

        expect($code)->toMatch('/^[A-Z0-9]+$/');
    });

    it('excludes confusing characters O/0/I/1', function () {
        $generator = new RoomCodeGenerator;

        // Generate many codes and check none contain excluded chars
        for ($i = 0; $i < 100; $i++) {
            $code = $generator->generate();

            expect($code)->not->toContain('O')->not->toContain('0')->not->toContain('I')->not->toContain('1');
        }
    });

    it('generates unique codes', function () {
        $generator = new RoomCodeGenerator;

        $codes = [];
        for ($i = 0; $i < 50; $i++) {
            $codes[] = $generator->generate();
        }

        // All codes should be unique
        expect(count(array_unique($codes)))->toBe(count($codes));
    });
});
