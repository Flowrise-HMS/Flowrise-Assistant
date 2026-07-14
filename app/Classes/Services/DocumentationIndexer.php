<?php

namespace Modules\AI\Classes\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\AI\Models\DocumentationChunk;

class DocumentationIndexer
{
    public function __construct(
        protected EmbeddingSearchService $searchService,
    ) {}

    public function index(?array $paths = null): int
    {
        $paths ??= config('ai.documentation.paths', []);
        $exclude = config('ai.documentation.exclude_paths', []);
        $chunkSize = (int) config('ai-assistant.embeddings.chunk_size', 800);
        $indexed = 0;

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'md') {
                    continue;
                }

                $absolute = $file->getPathname();
                if ($this->isExcluded($absolute, $exclude)) {
                    continue;
                }

                $relative = Str::replaceFirst(base_path().'/', '', $absolute);
                $content = File::get($absolute);
                $title = $this->extractTitle($content) ?? basename($relative, '.md');
                $audience = $this->detectAudience($relative);
                $module = $this->detectModule($relative);
                $chunks = $this->chunkContent($content, $chunkSize);

                foreach ($chunks as $index => $chunk) {
                    $hash = hash('sha256', $relative.'|'.$index.'|'.$chunk);

                    DocumentationChunk::query()->updateOrCreate(
                        ['path' => $relative, 'chunk_index' => $index],
                        [
                            'title' => $title,
                            'audience' => $audience,
                            'module' => $module,
                            'content' => $chunk,
                            'content_hash' => $hash,
                        ]
                    );

                    $indexed++;
                }
            }
        }

        return $indexed;
    }

    /**
     * @param  list<string>  $exclude
     */
    protected function isExcluded(string $path, array $exclude): bool
    {
        foreach ($exclude as $excludedPath) {
            if (Str::startsWith($path, rtrim($excludedPath, '/').'/') || $path === $excludedPath) {
                return true;
            }
        }

        return false;
    }

    protected function extractTitle(string $content): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $content, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function detectAudience(string $path): string
    {
        return match (true) {
            Str::contains($path, 'developer-guide') => 'developer',
            Str::contains($path, 'admin-guide') => 'admin',
            default => 'staff',
        };
    }

    protected function detectModule(string $path): ?string
    {
        if (preg_match('/docs\/user-guide\/([a-z-]+)/', $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function chunkContent(string $content, int $chunkSize): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        // Chunk by characters, not bytes — markdown trees use multi-byte glyphs (│, └, ├).
        if (mb_strlen($content) <= $chunkSize) {
            return [$content];
        }

        $chunks = [];
        $offset = 0;
        $length = mb_strlen($content);
        $overlap = (int) config('ai-assistant.embeddings.chunk_overlap', 100);
        $step = max(1, $chunkSize - $overlap);

        while ($offset < $length) {
            $chunks[] = mb_substr($content, $offset, $chunkSize);
            $offset += $step;
        }

        return $chunks;
    }
}
