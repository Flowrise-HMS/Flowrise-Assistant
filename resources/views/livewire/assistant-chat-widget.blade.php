<div
    x-data="assistantChatWidget({
        turnUrl: @js(route('ai.assistant.turn')),
        csrfToken: @js(csrf_token()),
        userId: @js(auth()->id()),
    })"
    x-init="boot()"
    x-on:assistant-turn-request.window="startTurn($event.detail.message, $event.detail.conversationId)"
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
                        @if ($entry['role'] === 'assistant')
                            <div class="flowrise-assistant__markdown">
                                {!! \Modules\AI\Classes\Support\AssistantMarkdown::toHtml($entry['content']) !!}
                            </div>
                        @else
                            <div class="flowrise-assistant__plain">{{ $entry['content'] }}</div>
                        @endif

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

                <div
                    x-show="streaming"
                    x-cloak
                    class="flowrise-assistant__message flowrise-assistant__message--assistant flowrise-assistant__message--streaming"
                >
                    <template x-if="streamBuffer.length === 0">
                        <div class="flowrise-assistant__status" x-text="statusLabel"></div>
                    </template>
                    <template x-if="streamBuffer.length > 0">
                        <div>
                            <span x-text="streamBuffer"></span><span class="flowrise-assistant__cursor" aria-hidden="true"></span>
                        </div>
                    </template>
                </div>
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
        turnUrl: config.turnUrl,
        csrfToken: config.csrfToken,
        userId: config.userId,
        streaming: false,
        streamBuffer: '',
        statusLabel: @js(__('Thinking…')),
        activeTurnId: null,
        userChannel: null,
        conversationChannel: null,

        boot() {
            if (! window.Echo || ! this.userId) {
                return;
            }

            this.userChannel = window.Echo.private(`ai.user.${this.userId}`)
                .listen('.assistant.turn.started', (event) => this.onTurnStarted(event))
                .listen('.assistant.token_chunk', (event) => this.onTokenChunk(event))
                .listen('.assistant.turn.completed', (event) => this.onTurnCompleted(event))
                .listen('.assistant.turn.failed', (event) => this.onTurnFailed(event));
        },

        async startTurn(message, conversationId) {
            this.streaming = true;
            this.streamBuffer = '';
            this.statusLabel = @js(__('Thinking…'));
            this.activeTurnId = null;
            $wire.beginThinking();
            queueMicrotask(() => this.scrollToBottom());

            if (! window.Echo) {
                $wire.markStreamError(@js(__('Realtime connection is unavailable. Refresh the page and ensure Reverb is running.')));
                this.resetStreamState();
                return;
            }

            try {
                if (conversationId) {
                    this.subscribeConversation(conversationId);
                }

                const response = await fetch(this.turnUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(window.Echo.socketId() ? { 'X-Socket-ID': window.Echo.socketId() } : {}),
                    },
                    body: JSON.stringify({
                        message,
                        conversation_id: conversationId,
                    }),
                    credentials: 'same-origin',
                });

                if (! response.ok) {
                    throw new Error('Turn failed');
                }

                const payload = await response.json();
                this.activeTurnId = payload.turn_id;
                this.statusLabel = @js(__('Generating response…'));

                if (payload.conversation_id) {
                    this.subscribeConversation(payload.conversation_id);
                }
            } catch (error) {
                $wire.markStreamError(@js(__('Could not start the assistant turn. Please try again.')));
                this.resetStreamState();
            }
        },

        subscribeConversation(conversationId) {
            if (! window.Echo || ! conversationId) {
                return;
            }

            if (this.conversationChannel && this.conversationChannel !== conversationId) {
                window.Echo.leave(`ai.conversation.${this.conversationChannel}`);
            }

            this.conversationChannel = conversationId;
            window.Echo.private(`ai.conversation.${conversationId}`)
                .listen('.assistant.turn.started', (event) => this.onTurnStarted(event))
                .listen('.assistant.token_chunk', (event) => this.onTokenChunk(event))
                .listen('.assistant.turn.completed', (event) => this.onTurnCompleted(event))
                .listen('.assistant.turn.failed', (event) => this.onTurnFailed(event));
        },

        onTurnStarted(event) {
            if (this.activeTurnId && event.turn_id && event.turn_id !== this.activeTurnId) {
                return;
            }

            this.streaming = true;
            this.statusLabel = @js(__('Generating response…'));
            this.scrollToBottom();
        },

        onTokenChunk(event) {
            if (this.activeTurnId && event.turn_id && event.turn_id !== this.activeTurnId) {
                return;
            }

            this.streaming = true;
            this.streamBuffer += event.delta ?? '';
            this.scrollToBottom();
        },

        onTurnCompleted(event) {
            if (this.activeTurnId && event.turn_id && event.turn_id !== this.activeTurnId) {
                return;
            }

            const text = event.text || this.streamBuffer;
            $wire.appendStreamedAssistantMessage(text, event.conversation_id ?? null);
            this.resetStreamState();
            queueMicrotask(() => this.scrollToBottom());
        },

        onTurnFailed(event) {
            if (this.activeTurnId && event.turn_id && event.turn_id !== this.activeTurnId) {
                return;
            }

            const message = (event.message || '').trim() || this.streamBuffer;
            $wire.markStreamError(message);
            this.resetStreamState();
            queueMicrotask(() => this.scrollToBottom());
        },

        resetStreamState() {
            this.streaming = false;
            this.streamBuffer = '';
            this.statusLabel = @js(__('Thinking…'));
            this.activeTurnId = null;
        },

        scrollToBottom() {
            const el = document.getElementById('assistant-messages');
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        },
    }));
</script>
@endscript
