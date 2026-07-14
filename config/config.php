<?php

return [
    'name' => 'AI',
    'permissions' => [
        'use_ai_assistant' => 'Use AI Assistant',
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
