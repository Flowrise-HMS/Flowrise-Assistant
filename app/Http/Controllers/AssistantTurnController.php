<?php

namespace Modules\AI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\AI\Classes\Support\AssistantBroadcast;
use Modules\AI\Classes\Support\AssistantPermission;
use Modules\AI\Classes\Support\Feature;
use Modules\AI\Jobs\StreamAssistantTurnJob;
use Modules\Core\Contracts\AssistantContract;
use Modules\Core\Models\CoreUser;

class AssistantTurnController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(app(AssistantContract::class)->isEnabled(), 404);
        abort_unless(Feature::assistantEnabled(), 404);
        abort_unless($request->user()?->can(AssistantPermission::UseAssistant), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof CoreUser, 403);

        $turnId = (string) Str::uuid();
        $conversationId = $validated['conversation_id'] ?? null;

        AssistantBroadcast::sendNow('assistant.turn.started', [
            'turn_id' => $turnId,
            'conversation_id' => $conversationId,
        ], $user->id, $conversationId);

        StreamAssistantTurnJob::dispatchSync(
            userId: $user->getAuthIdentifier(),
            message: $validated['message'],
            turnId: $turnId,
            conversationId: $conversationId,
        );

        return response()->json([
            'turn_id' => $turnId,
            'conversation_id' => $conversationId,
        ], 202);
    }
}
