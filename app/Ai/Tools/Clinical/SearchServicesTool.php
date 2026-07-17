<?php

namespace Modules\AI\Ai\Tools\Clinical;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\AI\Classes\Services\EmbeddingSearchService;
use Modules\AI\Classes\Services\ToolAuthorizationService;
use Modules\Core\Models\Service;
use Stringable;

class SearchServicesTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Search billable services and procedures available in FlowRise.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->moduleSupports('clinical')) {
            return $this->moduleUnavailable('clinical');
        }

        if (! app(ToolAuthorizationService::class)->userCanRun($this->user, 'search_services')) {
            return $this->denial('search_services');
        }

        $term = (string) ($request['query'] ?? '');
        $limit = (int) ($request['limit'] ?? 15);

        $results = Service::query()
            ->where('is_active', true)
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(max(1, min($limit, 25)))
            ->get(['id', 'code', 'name'])
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'code' => $service->code,
                'name' => $service->name,
            ])
            ->values()
            ->all();

        $embeddingResults = app(EmbeddingSearchService::class)
            ->searchEmbeddings('service', $term, 5)
            ->all();

        return $this->success([
            'results' => $results,
            'embedding_matches' => $embeddingResults,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
            'limit' => $schema->integer()->min(1)->max(25),
        ];
    }
}
