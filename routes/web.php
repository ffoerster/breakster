<?php

use App\Http\Controllers\Auth\TidalAuthController;
use App\Livewire\Room\Create;
use App\Livewire\Room\Join;
use App\Livewire\Room\Show;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/room/create', Create::class)->name('room.create');
Route::get('/room/join', Join::class)->name('room.join');
Route::get('/room/{code}', Show::class)
    ->where('code', '[A-Z0-9]{6,8}')
    ->name('room.show');

Route::middleware(['auth'])->group(function (): void {
    Route::get('/auth/tidal/connect', [TidalAuthController::class, 'connect'])->name('tidal.connect');
    Route::get('/auth/tidal/callback', [TidalAuthController::class, 'callback'])->name('tidal.callback');
    Route::post('/auth/tidal/disconnect', [TidalAuthController::class, 'disconnect'])->name('tidal.disconnect');
});
