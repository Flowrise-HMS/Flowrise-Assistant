# AI module

FlowRise assistant (agents, tools, documentation copilot).

Depends on **Core** (`flowrise-hms/core`) and Laravel AI. Remains installable beside Core without project-root PHP/config edits.

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
