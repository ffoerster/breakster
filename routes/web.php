<?php

use App\Http\Controllers\Auth\SpotifyAuthController;
use App\Http\Controllers\Auth\TidalAuthController;
use App\Http\Controllers\PackImageController;
use App\Http\Controllers\Play\PlayRedirectController;
use App\Http\Controllers\Room\RoomActionController;
use App\Http\Controllers\Room\RoomCreateController;
use App\Http\Controllers\Room\RoomJoinController;
use App\Http\Controllers\Room\RoomShowController;
use App\Http\Controllers\Room\RoomStateController;
use App\Http\Controllers\Room\RoomSummaryController;
use App\Http\Controllers\Room\SpotifyDeviceController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Broadcast::routes(['middleware' => ['web']]);

Route::get('/manifest.webmanifest', function () {
    $path = public_path('build/manifest.webmanifest');

    if (!is_file($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/manifest+json; charset=utf-8',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
})->name('pwa.manifest');

Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');

Route::get('/play/{roomCode}/{roundId}', PlayRedirectController::class)
    ->where('roomCode', '[A-Z0-9]{6,8}')
    ->whereNumber('roundId')
    ->name('play.redirect');

Route::get('/room/create', [RoomCreateController::class, 'create'])->name('room.create');
Route::post('/room/create', [RoomCreateController::class, 'store'])->name('room.create.store');
Route::get('/packs/{pack}/placeholder.svg', [PackImageController::class, 'placeholder'])->name('packs.placeholder');

Route::get('/room/join', [RoomJoinController::class, 'create'])->name('room.join');
Route::post('/room/join', [RoomJoinController::class, 'store'])->name('room.join.store');

Route::get('/room/{code}', RoomShowController::class)->where('code', '[A-Z0-9]{6,8}')->name('room.show');

Route::prefix('/room/{code}')
    ->where(['code' => '[A-Z0-9]{6,8}'])
    ->group(function (): void {
        Route::get('/state', RoomStateController::class)->name('room.state');
        Route::get('/summary', RoomSummaryController::class)->name('room.summary');

        Route::post('/provider', [RoomActionController::class, 'setPlaybackProvider'])->name('room.provider');
        Route::post('/placement-timer', [RoomActionController::class, 'setPlacementTimer'])->name(
            'room.placement-timer',
        );
        Route::post('/start', [RoomActionController::class, 'start'])->name('room.start');
        Route::post('/place', [RoomActionController::class, 'place'])->name('room.place');
        Route::post('/reveal', [RoomActionController::class, 'reveal'])->name('room.reveal');
        Route::post('/next', [RoomActionController::class, 'next'])->name('room.next');
        Route::post('/finish', [RoomActionController::class, 'finish'])->name('room.finish');
        Route::post('/ready', [RoomActionController::class, 'markReady'])->name('room.ready');

        // AMEN routes
        Route::post('/round/{roundId}/amen', [RoomActionController::class, 'claimAmen'])
            ->whereNumber('roundId')
            ->name('room.amen.claim');
        Route::post('/round/{roundId}/amen/guess', [RoomActionController::class, 'submitAmenGuess'])
            ->whereNumber('roundId')
            ->name('room.amen.guess');
        Route::post('/round/{roundId}/amen/vote', [RoomActionController::class, 'submitAmenVote'])
            ->whereNumber('roundId')
            ->name('room.amen.vote');

        // Spotify device management routes (auth checked in controller)
        Route::get('/spotify/devices', [SpotifyDeviceController::class, 'index'])->name('room.spotify.devices');
        Route::post('/spotify/device', [SpotifyDeviceController::class, 'store'])->name('room.spotify.device');
        Route::post('/spotify/play', [SpotifyDeviceController::class, 'play'])->name('room.spotify.play');
    });

Route::middleware(['auth'])->group(function (): void {
    // TIDAL OAuth routes
    Route::get('/auth/tidal/connect', [TidalAuthController::class, 'connect'])->name('tidal.connect');
    Route::get('/auth/tidal/callback', [TidalAuthController::class, 'callback'])->name('tidal.callback');
    Route::post('/auth/tidal/disconnect', [TidalAuthController::class, 'disconnect'])->name('tidal.disconnect');
});

// Spotify OAuth routes (authentication checked in controller)
Route::get('/auth/spotify/connect', [SpotifyAuthController::class, 'connect'])->name('spotify.connect');
Route::get('/auth/spotify/callback', [SpotifyAuthController::class, 'callback'])->name('spotify.callback');
Route::post('/auth/spotify/disconnect', [SpotifyAuthController::class, 'disconnect'])->name('spotify.disconnect');
