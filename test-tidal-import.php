<?php

use App\Domain\Providers\Tidal\TidalClient;
use App\Jobs\ImportTidalTracksJob;
use App\Models\Pack;

// Create a test pack
$pack = Pack::factory()->create(['name' => 'Test TIDAL Import']);

echo "Pack created: {$pack->id}\n";

// Dispatch the job
ImportTidalTracksJob::dispatch($pack->id, 'https://tidal.com/browse/playlist/fa2d2386-5838-45b0-8dff-e8c26dca23db');

echo "Job dispatched\n";

// Process the job immediately
try {
    // Test with the playlist URL
    $playlistUrl = 'https://tidal.com/playlist/fa2d2386-5838-45b0-8dff-e8c26dca23db';
    $job = new ImportTidalTracksJob($pack->id, $playlistUrl);
    $client = app(TidalClient::class);
    echo "Client resolved successfully\n";
    echo "Fetching from: {$playlistUrl}\n";
    $job->handle($client);
    echo "Job handled successfully\n";
    echo 'Tracks imported: '.$pack->tracks()->count()."\n";
} catch (\Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}
