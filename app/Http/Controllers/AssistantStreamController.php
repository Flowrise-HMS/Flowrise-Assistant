<?php

namespace Modules\AI\Http\Controllers;

use Illuminate\Http\Request;
use Modules\AI\Classes\Services\AssistantOrchestratorService;
use Modules\AI\Classes\Support\AssistantPermission;
use Modules\AI\Classes\Support\Feature;
use Modules\Core\Contracts\AssistantContract;

class AssistantStreamController
{
    public function __invoke(Request $request, AssistantOrchestratorService $orchestrator)
    {
        abort_unless(app(AssistantContract::class)->isEnabled(), 404);
        abort_unless(Feature::assistantEnabled(), 404);
        abort_unless($request->user()?->can(AssistantPermission::UseAssistant), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'string'],
        ]);

        return $orchestrator->stream(
            user: $request->user(),
            message: $validated['message'],
            conversationId: $validated['conversation_id'] ?? null,
        );
    }
}
