<?php

return [
    'name' => 'AI',
    'permissions' => [
        'use_ai_assistant' => 'Use AI Assistant',
    ],
    /*
    |--------------------------------------------------------------------------
    | Realtime (Filament Echo)
    |--------------------------------------------------------------------------
    |
    | Expanded assistant streaming uses private Reverb/Echo channels. When the
    | host has not already configured filament.broadcasting.echo (no app key),
    | the AI module merges these defaults so the panel gets window.Echo without
    | depending on a project-root filament.php edit.
    |
    | Host still owns Laravel broadcasting itself (BROADCAST_CONNECTION,
    | REVERB_*, queue worker, reverb:start).
    |
    */
    'broadcasting' => [
        'configure_filament_echo' => env('AI_CONFIGURE_FILAMENT_ECHO', true),
        'echo' => [
            'broadcaster' => 'reverb',
            'key' => env('VITE_REVERB_APP_KEY', env('REVERB_APP_KEY')),
            'wsHost' => env('VITE_REVERB_HOST', env('REVERB_HOST', 'localhost')),
            'wsPort' => env('VITE_REVERB_PORT', env('REVERB_PORT', 8080)),
            'wssPort' => env('VITE_REVERB_PORT', env('REVERB_PORT', 8080)),
            'authEndpoint' => '/broadcasting/auth',
            'disableStats' => true,
            'encrypted' => true,
            'forceTLS' => env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'https')) === 'https',
            'enabledTransports' => ['ws', 'wss'],
        ],
    ],
    'documentation' => [
        'paths' => [
            base_path('docs/user-guide'),
            base_path('docs/shared'),
            base_path('docs/admin-guide'),
        ],
        'exclude_paths' => [
            base_path('docs/superpowers'),
            base_path('docs/developer-guide'),
        ],
        'audiences' => [
            'staff' => ['user-guide', 'shared', 'admin-guide'],
            'developer' => ['developer-guide', 'shared'],
        ],
    ],
];
