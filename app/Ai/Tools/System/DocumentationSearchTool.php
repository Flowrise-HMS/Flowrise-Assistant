<?php

namespace Modules\AI\Ai\Tools\System;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\Ai\Tools\Concerns\InteractsWithAssistantUser;
use Modules\AI\Classes\Services\EmbeddingSearchService;
use Modules\AI\Classes\Support\Feature;
use Stringable;

class DocumentationSearchTool implements Tool
{
    use InteractsWithAssistantUser;

    public function description(): Stringable|string
    {
        return 'Search FlowRise documentation for how-to guides, navigation help, and workflow instructions.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! Feature::helpDeskEnabled()) {
            return $this->moduleUnavailable('documentation help desk');
        }

        $this->authorizeTool('documentation_search');

        $query = (string) ($request['query'] ?? '');
        $limit = (int) ($request['limit'] ?? config('ai-assistant.documentation_search.limit', 8));

        $results = app(EmbeddingSearchService::class)
            ->searchDocumentation($query, max(1, min($limit, 15)), 'staff')
            ->values()
            ->all();

        return $this->success([
            'query' => $query,
            'results' => $results,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('Natural language question or keywords'),
            'limit' => $schema->integer()->min(1)->max(15),
        ];
    }
}
