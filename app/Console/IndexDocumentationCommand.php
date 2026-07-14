<?php

namespace Modules\AI\Console;

use Illuminate\Console\Command;
use Modules\AI\Classes\Services\DocumentationIndexer;
use Modules\AI\Classes\Services\OperationalEmbeddingIndexer;

class IndexDocumentationCommand extends Command
{
    protected $signature = 'ai:index-docs {--embeddings : Also refresh operational embeddings for diagnosis codes and services}';

    protected $description = 'Index FlowRise documentation for the AI help desk copilot';

    public function handle(DocumentationIndexer $indexer, OperationalEmbeddingIndexer $embeddingIndexer): int
    {
        $count = $indexer->index();
        $this->info("Indexed {$count} documentation chunks.");

        if ($this->option('embeddings')) {
            $embeddingCount = $embeddingIndexer->index();
            $this->info("Indexed {$embeddingCount} operational embeddings.");
        }

        return self::SUCCESS;
    }
}
