# AI module

FlowRise assistant (agents, tools, documentation copilot).

**Status:** Complete — see [Module Status](../../docs/shared/module-status.md). Verified against code on 2026-09-20.

Depends on **Core** (`flowrise-hms/core`) and Laravel AI (`laravel/ai`; provider configuration in the host `config/ai.php`).

## What it does

- Renders the **FlowRise assistant** as a floating chat widget (`Modules\AI\Livewire\AssistantChatWidget`, layouts closed / compact / expanded; floating button **Open FlowRise Assistant**, panel title **FlowRise Assistant**, input **Ask FlowRise Assistant...**) at the bottom of every Filament panel page via render hooks. The module registers no cluster, resources or pages of its own.
- Routes each turn to role-aware agents: `FlowRiseAssistantAgent` (router), `HelpDeskAgent` (documentation copilot over the indexed docs), `ClinicalDocumentationAgent`, `SchedulingAgent`, `BillingAgent`, with tools under `app/Ai/Tools/{Patients,Clinical,Appointments,Billing,System}` (search patients, search documentation, open a Filament page, propose/execute clinical notes, bookings and billing actions).
- Guards every turn with middleware: `EnforcePermissionsMiddleware` (tools only act within the user's Shield permissions), `RedactPhiMiddleware`, `AuditPromptMiddleware` (rows in `assistant_audit_logs`).

## Enabling it

1. The widget is **off by default**. Turn on **AI assistant** under Administration → System → Features (`FeatureSettings::$ai_assistant_enabled`); the same page has toggles for **Documentation help desk**, **Clinical copilot** and **AI write actions (with confirmation)**.
2. Grant the custom permission `use_ai_assistant` ("Use AI Assistant") to the roles that may chat.
3. Configure a text provider in the host `.env`: the default provider is **Gemini** (`GEMINI_API_KEY`, optional `GEMINI_TEXT_MODEL`, default `gemini-2.0-flash`); `config/ai.php` also supports OpenAI, Anthropic, Azure, Bedrock, DeepSeek, Groq, Mistral, Ollama, OpenRouter and xAI. Embeddings default to OpenAI (`OPENAI_API_KEY`) and reranking to Cohere (`COHERE_API_KEY`); both are only needed for the `--embeddings` index.
4. Run `php artisan ai:index-docs` (optionally `--embeddings`) to index `docs/user-guide`, `docs/shared` and `docs/admin-guide` into `documentation_chunks` for the help desk (developer docs and `docs/superpowers` are excluded). Re-run it after documentation changes; nothing schedules it.
5. Provide the Reverb/broadcasting stack described below so replies stream.

Turns are posted to `POST /assistant/turn` (web + auth; 404 when the feature is off, 403 without the permission) and processed synchronously in the request by `StreamAssistantTurnJob` (no queue worker needed for the turn itself; tokens are broadcast over Reverb as they arrive).

Note: the keys in `Modules/AI/config/ai-assistant.php` (`AI_PHI_REDACTION_ENABLED`, `AI_AUDIT_ENABLED`, `AI_AUDIT_RETENTION_DAYS`) are currently inert because the file is merged as `config('ai.ai-assistant')` while the services read `config('ai-assistant.*')`; the built-in defaults (redaction on, audit on) always apply.

Tables: `assistant_audit_logs`, `documentation_chunks`, `assistant_embeddings`. Tests: `php artisan test --compact Modules/AI/tests` (12 files).

## Assets (Laravel Modules + Vite)

This host uses the **central Vite** approach from [Laravel Modules compiling assets](https://laravelmodules.com/docs/13/basic-usage/compiling-assets) (same as Core):

- Source CSS: `resources/assets/css/assistant-widget.css`
- Module `vite.config.js` exports `paths` only (picked up by root `vite-module-loader.js`)
- Filament loads that entry via `Vite::withEntryPoints` on `panels::styles.after`
- Build with root `pnpm run build` or `composer run dev`

Do **not** also use a per-module `build-ai` / `module_vite()` pipeline — Modules docs say not to mix central and independent modes. Empty stub `resources/assets/sass` / `js` remain for future assets and are omitted from `paths` until used.

## Realtime (Reverb)

Expanded chat streams tokens over **Laravel Reverb** + Filament Echo (not SSE). Compact and expanded both stream tokens progressively with a thinking/typing indicator.

### Host contract (not AI source files)

The host Laravel app must provide broadcasting infrastructure—AI does not own Reverb package wiring:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=flowrise
REVERB_APP_KEY=local-reverb-key
REVERB_APP_SECRET=local-reverb-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

```bash
php artisan reverb:start
php artisan queue:work
```

### Filament Echo (module-owned defaults)

AI registers private channels from `Modules/AI/routes/channels.php` and, unless the host already set `filament.broadcasting.echo.key`, merges Echo client defaults from `config('ai.broadcasting.echo')` so `window.Echo` is available in Filament panels.

- Disable with `AI_CONFIGURE_FILAMENT_ECHO=false` or `ai.broadcasting.configure_filament_echo`
- Host Echo config with a key always wins

### Protocol

- Turn endpoint: `POST` named route `ai.assistant.turn` → `202` `{ turn_id, conversation_id }`
- Private channels: `ai.user.{id}`, `ai.conversation.{id}` (authorized against `CoreUser`)
- Events: `.assistant.turn.started`, `.assistant.token_chunk`, `.assistant.turn.completed`, `.assistant.turn.failed`
