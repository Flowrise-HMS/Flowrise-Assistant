<?php

namespace Modules\AI\Ai\Tools\Clinical;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\AI\Classes\Services\EmbeddingSearchService;
use Modules\AI\Classes\Services\ToolAuthorizationService;
use Modules\Clinical\Classes\Services\DiagnosisCodeService;
use Stringable;

class SearchDiagnosisCodesTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Search ICD diagnosis codes by code or description. Returns AI suggestions only — verify clinically.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->moduleSupports('clinical')) {
            return $this->moduleUnavailable('clinical');
        }

        if (! app(ToolAuthorizationService::class)->userCanRun($this->user, 'search_diagnosis_codes')) {
            return $this->denial('search_diagnosis_codes');
        }

        $term = (string) ($request['query'] ?? '');
        $limit = (int) ($request['limit'] ?? 15);

        $results = app(DiagnosisCodeService::class)
            ->search($term, (bool) ($request['nhis_only'] ?? false), max(1, min($limit, 25)))
            ->map(fn ($code) => [
                'code' => $code->code,
                'description' => $code->description,
                'nhis_covered' => $code->nhis_covered,
            ])
            ->values()
            ->all();

        $embeddingResults = app(EmbeddingSearchService::class)
            ->searchEmbeddings('diagnosis_code', $term, 5)
            ->all();

        return $this->success([
            'results' => $results,
            'embedding_matches' => $embeddingResults,
            'disclaimer' => 'AI suggestion — verify clinically.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
            'nhis_only' => $schema->boolean(),
            'limit' => $schema->integer()->min(1)->max(25),
        ];
    }
}
