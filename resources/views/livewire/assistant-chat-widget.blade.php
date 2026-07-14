<div
    x-data="assistantChatWidget({
        streamUrl: @js(route('ai.assistant.stream')),
        csrfToken: @js(csrf_token()),
    })"
    x-on:assistant-stream-request.window="streamMessage($event.detail.message, $event.detail.conversationId)"
    class="flowrise-assistant"
>
    @if ($layout === 'closed')
        <button
            type="button"
            wire:click="open"
            class="flowrise-assistant__fab"
            aria-label="{{ __('Open FlowRise Assistant') }}"
        >
            <x-filament::icon
                icon="heroicon-o-chat-bubble-left-right"
                class="flowrise-assistant__fab-icon"
            />
        </button>
    @else
        <div @class([
            'flowrise-assistant__panel',
            'flowrise-assistant__panel--compact' => $layout === 'compact',
            'flowrise-assistant__panel--expanded' => $layout === 'expanded',
        ])>
            <div class="flowrise-assistant__header">
                <div class="flowrise-assistant__header-info">
                    <p class="flowrise-assistant__title">{{ __('FlowRise Assistant') }}</p>
                    <p class="flowrise-assistant__subtitle">{{ __('Documentation, scheduling, and clinical help') }}</p>
                </div>
                <div class="flowrise-assistant__actions">
                    <button
                        type="button"
                        wire:click="startNewChat"
                        class="flowrise-assistant__icon-action"
                        title="{{ __('New chat') }}"
                        aria-label="{{ __('New chat') }}"
                    >
                        <x-filament::icon icon="heroicon-o-plus" class="flowrise-assistant__icon-action-icon" />
                    </button>
                    <button
                        type="button"
                        wire:click="toggleExpand"
                        class="flowrise-assistant__icon-action"
                        title="{{ $layout === 'expanded' ? __('Compact') : __('Expand') }}"
                        aria-label="{{ $layout === 'expanded' ? __('Compact') : __('Expand') }}"
                    >
                        <x-filament::icon
                            :icon="$layout === 'expanded' ? 'heroicon-o-arrows-pointing-in' : 'heroicon-o-arrows-pointing-out'"
                            class="flowrise-assistant__icon-action-icon"
                        />
                    </button>
                    <button
                        type="button"
                        wire:click="close"
                        class="flowrise-assistant__icon-action"
                        title="{{ __('Close') }}"
                        aria-label="{{ __('Close') }}"
                    >
                        <x-filament::icon icon="heroicon-o-x-mark" class="flowrise-assistant__icon-action-icon" />
                    </button>
                </div>
            </div>

            <div class="flowrise-assistant__messages" id="assistant-messages">
                @forelse ($messages as $index => $entry)
                    <div @class([
                        'flowrise-assistant__message',
                        'flowrise-assistant__message--user' => $entry['role'] === 'user',
                        'flowrise-assistant__message--assistant' => $entry['role'] === 'assistant',
                    ])>
                        <div>{{ $entry['content'] }}</div>

                        @if (! empty($entry['proposal']))
                            <div class="flowrise-assistant__proposal">
                                <p class="mb-2 font-medium">{{ __('Confirm this action') }}</p>
                                <pre class="mb-3 overflow-x-auto whitespace-pre-wrap font-sans">{{ json_encode($entry['proposal']['preview'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                <div class="flowrise-assistant__proposal-actions">
                                    <button
                                        type="button"
                                        wire:click="approveProposal({{ $index }}, @js($entry['proposal']))"
                                        wire:loading.attr="disabled"
                                        class="flowrise-assistant__approve"
                                    >
                                        {{ __('Approve') }}
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="cancelProposal({{ $index }})"
                                        wire:loading.attr="disabled"
                                        class="flowrise-assistant__cancel"
                                    >
                                        {{ __('Cancel') }}
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="flowrise-assistant__empty">
                        <p class="flowrise-assistant__empty-text">
                            {{ __('Ask how to enter vitals, schedule appointments, or navigate FlowRise.') }}
                        </p>
                        <div class="flowrise-assistant__prompts">
                            @foreach ($this->quickPrompts() as $prompt)
                                <button
                                    type="button"
                                    wire:click="sendQuickPrompt(@js($prompt['message']))"
                                    class="flowrise-assistant__prompt"
                                >
                                    {{ $prompt['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforelse

                <div x-show="streaming" x-cloak class="flowrise-assistant__message flowrise-assistant__message--assistant">
                    <span x-text="streamBuffer"></span>
                    <span class="inline-block animate-pulse">▍</span>
                </div>

                @if ($isThinking && $layout !== 'expanded')
                    <div class="flowrise-assistant__thinking">
                        {{ __('Thinking…') }}
                    </div>
                @endif
            </div>

            <form wire:submit="send" class="flowrise-assistant__composer">
                <input
                    type="text"
                    wire:model="message"
                    @disabled($isThinking)
                    placeholder="{{ __('Ask FlowRise Assistant…') }}"
                    class="flowrise-assistant__input"
                />
                <button
                    type="submit"
                    @disabled($isThinking)
                    class="flowrise-assistant__send"
                >
                    {{ __('Send') }}
                </button>
            </form>
        </div>
    @endif
</div>

@script
<script>
    Alpine.data('assistantChatWidget', (config) => ({
        streamUrl: config.streamUrl,
        csrfToken: config.csrfToken,
        streaming: false,
        streamBuffer: '',

        async streamMessage(message, conversationId) {
            this.streaming = true;
            this.streamBuffer = '';
            $wire.beginThinking();

            try {
                const response = await fetch(this.streamUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'text/event-stream',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        message,
                        conversation_id: conversationId,
                    }),
                    credentials: 'same-origin',
                });

                if (!response.ok || !response.body) {
                    throw new Error('Stream failed');
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) {
                        break;
                    }

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() ?? '';

                    for (const line of lines) {
                        if (!line.startsWith('data:')) {
                            continue;
                        }

                        const payload = line.slice(5).trim();
                        if (payload === '' || payload === '[DONE]') {
                            continue;
                        }

                        try {
                            const event = JSON.parse(payload);
                            if (event.type === 'text-delta' && event.delta) {
                                this.streamBuffer += event.delta;
                            }
                        } catch (error) {
                            // Ignore malformed stream chunks.
                        }
                    }
                }

                $wire.appendStreamedAssistantMessage(this.streamBuffer, conversationId);
            } catch (error) {
                $wire.markStreamError(this.streamBuffer);
            } finally {
                this.streaming = false;
                this.streamBuffer = '';
            }
        },
    }));
</script>
@endscript
