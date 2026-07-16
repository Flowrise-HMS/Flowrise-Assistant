<?php

namespace Modules\AI\Tests\Unit;

use Modules\AI\Classes\Support\AssistantMarkdown;

class AssistantMarkdownTest extends AITestCase
{
    public function test_it_renders_markdown_to_safe_html(): void
    {
        $html = AssistantMarkdown::toHtml("**Bold** and a list:\n\n- One\n- Two");

        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringContainsString('<li>One</li>', $html);
        $this->assertStringContainsString('<li>Two</li>', $html);
    }

    public function test_it_strips_raw_html_from_markdown(): void
    {
        $html = AssistantMarkdown::toHtml('Hello <script>alert("xss")</script> world');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('alert', $html);
    }
}
