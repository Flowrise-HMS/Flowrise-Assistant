<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AssistantEmbedding extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_type',
        'source_id',
        'label',
        'content',
        'embedding',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }
}
