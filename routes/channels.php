<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Music Workflow Notifications Channel
Broadcast::channel('music-workflow-notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Role-based notification channels
Broadcast::channel('producer-notifications', function ($user) {
    return $user->role === 'Producer';
});

Broadcast::channel('music-arranger-notifications', function ($user) {
    return $user->role === 'Music Arranger';
});

Broadcast::channel('sound-engineer-notifications', function ($user) {
    return $user->role === 'Sound Engineer';
});

Broadcast::channel('creative-notifications', function ($user) {
    return $user->role === 'Creative';
});