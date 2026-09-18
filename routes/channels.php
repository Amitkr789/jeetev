<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatConversation;
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('admin-chat-list', function ($admin) {
    return (bool) $admin;
}, ['guards' => ['admin']]);

// Anyone assigned/handling, or a super_admin, can listen to a specific
// conversation's live message stream. Everyone else is denied.
Broadcast::channel('chat.conversation.{conversationId}', function ($admin, $conversationId) {
    if ($admin->role === 'super_admin') {
        return true;
    }

    $conversation = ChatConversation::find($conversationId);

    return $conversation && in_array($admin->id, [$conversation->handling_admin_id, $conversation->assigned_admin_id]);
}, ['guards' => ['admin']]);
/*
|--------------------------------------------------------------------------
| ADD TO routes/channels.php
|--------------------------------------------------------------------------
| If routes/channels.php doesn't exist yet, create it and make sure
| BroadcastServiceProvider is registered in config/app.php (it is by
| default in a fresh Laravel install — just uncomment it if needed).
*/

// Every logged-in admin can see the shared inbox list update live.
// Tighten this (e.g. only admins with a certain role) if needed.
