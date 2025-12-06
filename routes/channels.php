<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Jan Chat private channel authorization
Broadcast::channel('jan-chat.{userId}.{conversationId}', function ($user, $userId, $conversationId) {
    return (int) $user->id === (int) $userId;
});
