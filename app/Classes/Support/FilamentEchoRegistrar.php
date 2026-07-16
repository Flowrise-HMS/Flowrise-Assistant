<?php

namespace Modules\AI\Classes\Support;

/**
 * Fills Filament's Echo client config from AI module defaults when the host
 * has not already configured filament.broadcasting.echo with an app key.
 */
final class FilamentEchoRegistrar
{
    public static function register(): void
    {
        if (! config('ai.broadcasting.configure_filament_echo', true)) {
            return;
        }

        $moduleEcho = config('ai.broadcasting.echo', []);
        if (! is_array($moduleEcho) || $moduleEcho === []) {
            return;
        }

        $existing = config('filament.broadcasting.echo');
        if (is_array($existing) && filled($existing['key'] ?? null)) {
            return;
        }

        $existingFilled = collect(is_array($existing) ? $existing : [])
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();

        config([
            'filament.broadcasting.echo' => array_replace_recursive($moduleEcho, $existingFilled),
        ]);
    }
}
