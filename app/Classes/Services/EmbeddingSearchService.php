<?php

namespace Modules\AI\Classes\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\AI\Models\AssistantEmbedding;
use Modules\AI\Models\DocumentationChunk;

class EmbeddingSearchService
{
    public function searchDocumentation(string $query, int $limit = 8, ?string $audience = 'staff'): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        $chunks = DocumentationChunk::query()
            ->when($audience, fn ($builder) => $builder->where('audience', $audience))
            ->get();

        return $this->rankResults($chunks, $query, $limit)
            ->map(fn (DocumentationChunk $chunk): array => [
                'path' => $chunk->path,
                'title' => $chunk->title,
                'excerpt' => Str::limit($chunk->content, 400),
                'score' => $chunk->search_score ?? 0,
            ]);
    }

    public function searchEmbeddings(string $sourceType, string $query, int $limit = 10): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        $records = AssistantEmbedding::query()
            ->where('source_type', $sourceType)
            ->get();

        return $this->rankResults($records, $query, $limit)
            ->map(fn (AssistantEmbedding $record): array => [
                'source_type' => $record->source_type,
                'source_id' => $record->source_id,
                'label' => $record->label,
                'content' => $record->content,
                'score' => $record->search_score ?? 0,
            ]);
    }

    /**
     * @param  Collection<int, DocumentationChunk|AssistantEmbedding>  $records
     * @return Collection<int, DocumentationChunk|AssistantEmbedding>
     */
    protected function rankResults(Collection $records, string $query, int $limit): Collection
    {
        $terms = collect(explode(' ', Str::lower($query)))
            ->filter()
            ->values();

        return $records
            ->map(function ($record) use ($terms, $query) {
                $content = Str::lower((string) $record->content);
                $label = Str::lower((string) ($record->label ?? $record->title ?? ''));
                $keywordScore = 0.0;

                foreach ($terms as $term) {
                    if (Str::contains($content, $term)) {
                        $keywordScore += 0.2;
                    }
                    if ($label !== '' && Str::contains($label, $term)) {
                        $keywordScore += 0.3;
                    }
                }

                if (Str::contains($content, Str::lower($query))) {
                    $keywordScore += 0.25;
                }

                $vectorScore = 0.0;
                if (is_array($record->embedding) && $record->embedding !== []) {
                    $vectorScore = $this->cosineSimilarityFromText($query, $record->embedding);
                }

                $record->search_score = max($keywordScore, $vectorScore);

                return $record;
            })
            ->filter(fn ($record): bool => ($record->search_score ?? 0) >= (float) config('ai-assistant.documentation_search.min_score', 0.15))
            ->sortByDesc('search_score')
            ->take($limit)
            ->values();
    }

    /**
     * @param  list<float>  $embedding
     */
    protected function cosineSimilarityFromText(string $query, array $embedding): float
    {
        $queryVector = $this->pseudoEmbedding($query, count($embedding));

        return $this->cosineSimilarity($queryVector, $embedding);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        $length = min(count($a), count($b));
        if ($length === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * @return list<float>
     */
    protected function pseudoEmbedding(string $text, int $dimensions): array
    {
        $vector = array_fill(0, $dimensions, 0.0);
        $tokens = preg_split('/\s+/', Str::lower($text)) ?: [];

        foreach ($tokens as $token) {
            $index = crc32($token) % $dimensions;
            $vector[$index] += 1.0;
        }

        $magnitude = sqrt(array_sum(array_map(fn (float $v): float => $v ** 2, $vector)));

        if ($magnitude > 0) {
            $vector = array_map(fn (float $v): float => $v / $magnitude, $vector);
        }

        return $vector;
    }

    /**
     * @return list<float>
     */
    public function pseudoEmbeddingForStorage(string $text): array
    {
        return $this->pseudoEmbedding($text, (int) config('ai-assistant.embeddings.dimensions', 1536));
    }
}
