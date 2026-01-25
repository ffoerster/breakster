<?php

use App\Domain\Providers\Tidal\TidalClient;
use App\Jobs\ImportTidalTracksJob;
use App\Models\Pack;
use App\Models\Track;

it('imports TIDAL tracks into a pack (mocked)', function () {
    config()->set('services.tidal.mock', true);

    $pack = Pack::factory()->create();

    $job = new ImportTidalTracksJob($pack->id, 'playlist:demo');
    $job->handle(app(TidalClient::class));

    expect(Track::query()->where('pack_id', $pack->id)->count())->toBe(25);
    expect($pack->fresh()->tidal_source)->toBe('playlist:demo');
});

it('does not create duplicates when importing the same source twice', function () {
    config()->set('services.tidal.mock', true);

    $pack = Pack::factory()->create();

    $job = new ImportTidalTracksJob($pack->id, 'playlist:demo');
    $job->handle(app(TidalClient::class));

    $job2 = new ImportTidalTracksJob($pack->id, 'playlist:demo');
    $job2->handle(app(TidalClient::class));

    expect(Track::query()->where('pack_id', $pack->id)->count())->toBe(25);
});
