<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\CoreUser;

class AssistantAuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'branch_id',
        'conversation_id',
        'prompt_redacted',
        'response_summary',
        'tools_invoked',
        'records_affected',
        'provider',
        'model',
        'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'tools_invoked' => 'array',
            'records_affected' => 'array',
            'latency_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(CoreUser::class, 'user_id');
    }
}
