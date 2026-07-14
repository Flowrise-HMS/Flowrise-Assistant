<?php

namespace Modules\AI\Classes\Services;

use Modules\AI\Models\AssistantEmbedding;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Core\Models\Service;

class OperationalEmbeddingIndexer
{
    public function index(): int
    {
        $count = 0;

        DiagnosisCode::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->chunkById(200, function ($codes) use (&$count) {
                foreach ($codes as $code) {
                    $content = trim("{$code->code} {$code->description}");
                    AssistantEmbedding::query()->updateOrCreate(
                        ['source_type' => 'diagnosis_code', 'source_id' => (string) $code->id],
                        [
                            'label' => $code->code,
                            'content' => $content,
                            'content_hash' => hash('sha256', $content),
                            'embedding' => app(EmbeddingSearchService::class)->pseudoEmbeddingForStorage($content),
                        ]
                    );
                    $count++;
                }
            });

        Service::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->chunkById(200, function ($services) use (&$count) {
                foreach ($services as $service) {
                    $content = trim("{$service->code} {$service->name}");
                    AssistantEmbedding::query()->updateOrCreate(
                        ['source_type' => 'service', 'source_id' => (string) $service->id],
                        [
                            'label' => (string) $service->code,
                            'content' => $content,
                            'content_hash' => hash('sha256', $content),
                            'embedding' => app(EmbeddingSearchService::class)->pseudoEmbeddingForStorage($content),
                        ]
                    );
                    $count++;
                }
            });

        return $count;
    }
}
