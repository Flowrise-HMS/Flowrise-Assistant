<?php

namespace Modules\AI\Tests\Unit;

use Illuminate\Support\Str;
use Modules\AI\Classes\Services\DocumentationIndexer;
use Modules\AI\Models\DocumentationChunk;
use ReflectionMethod;

class DocumentationIndexerTest extends AITestCase
{
    public function test_chunk_content_does_not_split_multibyte_characters(): void
    {
        $indexer = app(DocumentationIndexer::class);
        $method = new ReflectionMethod(DocumentationIndexer::class, 'chunkContent');

        // Box-drawing chars are 3 bytes each; a byte-based substr at 800 would corrupt UTF-8.
        $glyph = '│';
        $content = str_repeat('a', 798).$glyph.str_repeat('b', 50);

        $chunks = $method->invoke($indexer, $content, 800);

        $this->assertNotEmpty($chunks);

        foreach ($chunks as $chunk) {
            $this->assertTrue(mb_check_encoding($chunk, 'UTF-8'));
        }

        $this->assertStringContainsString($glyph, implode('', $chunks));
    }

    public function test_index_accepts_decision_tree_markdown_with_box_drawing(): void
    {
        $path = storage_path('framework/testing/ai-docs');
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $file = $path.'/decision-tree.md';
        file_put_contents($file, <<<'MD'
# Which Guide Should I Read?

├─ Clinical Staff
│    └─ Quick Reference Guide
│
└─ System Administrator
     └─ Installation Guide
MD
        );

        $relative = Str::replaceFirst(base_path().'/', '', $file);
        $indexed = app(DocumentationIndexer::class)->index([$path]);

        $this->assertGreaterThan(0, $indexed);
        $this->assertDatabaseHas('documentation_chunks', [
            'path' => $relative,
            'title' => 'Which Guide Should I Read?',
        ]);

        $chunk = DocumentationChunk::query()->where('path', $relative)->first();

        $this->assertNotNull($chunk);
        $this->assertStringContainsString('│', $chunk->content);

        @unlink($file);
        @rmdir($path);
    }
}
