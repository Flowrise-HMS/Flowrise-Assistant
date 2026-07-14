<?php

namespace Modules\AI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DocumentationChunk extends Model
{
    use HasUuids;

    protected $fillable = [
        'path',
        'title',
        'audience',
        'module',
        'chunk_index',
        'content',
        'embedding',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'chunk_index' => 'integer',
        ];
    }
}
