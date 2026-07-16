<?php

namespace Modules\AI\Classes\Support;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;

final class AssistantBroadcast
{
    public static function userChannelName(int|string $userId): string
    {
        return 'ai.user.'.$userId;
    }

    public static function conversationChannelName(string $conversationId): string
    {
        return 'ai.conversation.'.$conversationId;
    }

    public static function userChannel(int|string $userId): PrivateChannel
    {
        return new PrivateChannel(self::userChannelName($userId));
    }

    public static function conversationChannel(string $conversationId): PrivateChannel
    {
        return new PrivateChannel(self::conversationChannelName($conversationId));
    }

    /**
     * @return list<PrivateChannel>
     */
    public static function channelsFor(int|string $userId, ?string $conversationId): array
    {
        $channels = [self::userChannel($userId)];

        if (filled($conversationId)) {
            $channels[] = self::conversationChannel($conversationId);
        }

        return $channels;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function sendNow(string $as, array $payload, int|string $userId, ?string $conversationId = null): void
    {
        Broadcast::on(self::channelsFor($userId, $conversationId))
            ->as($as)
            ->with($payload)
            ->sendNow();
    }
}
