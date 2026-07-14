<?php

return [
    'phi_redaction' => [
        'enabled' => env('AI_PHI_REDACTION_ENABLED', true),
        'token_prefix' => 'REF',
    ],
    'audit' => [
        'enabled' => env('AI_AUDIT_ENABLED', true),
        'retention_days' => (int) env('AI_AUDIT_RETENTION_DAYS', 365),
    ],
    'documentation_search' => [
        'limit' => 8,
        'min_score' => 0.15,
    ],
    'embeddings' => [
        'dimensions' => 1536,
        'chunk_size' => 800,
        'chunk_overlap' => 100,
    ],
];
