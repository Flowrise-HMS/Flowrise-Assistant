<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\CoreUser;

Broadcast::channel('ai.user.{userId}', function (CoreUser $user, string $userId): bool {
    return (string) $user->getAuthIdentifier() === (string) $userId;
});

Broadcast::channel('ai.conversation.{conversationId}', function (CoreUser $user, string $conversationId): bool {
    $table = config('ai.conversations.tables.conversations', 'agent_conversations');

    return DB::table($table)
        ->where('id', $conversationId)
        ->where('user_id', $user->getAuthIdentifier())
        ->exists();
});
