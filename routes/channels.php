<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 |--------------------------------------------------------------------------
 | Room Broadcast Channels
 |--------------------------------------------------------------------------
 |
 | Room channels allow real-time updates for game state changes.
 | Using public channels for MVP to avoid anonymous player auth complexity.
 | Channels are scoped by room code to prevent cross-room data leaks.
 | TODO: Upgrade to private channels with session-based player auth for production.
 |
 */

Broadcast::channel('room.{roomCode}', function ($user, string $roomCode) {
    // Public channel for MVP - anyone can subscribe
    // Room code scoping prevents most cross-room issues
    // Payloads are kept minimal and do not expose sensitive data
    return true;
});
