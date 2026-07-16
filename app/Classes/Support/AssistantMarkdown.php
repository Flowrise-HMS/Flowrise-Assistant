<?php

namespace Modules\AI\Classes\Support;

use Illuminate\Support\Str;

final class AssistantMarkdown
{
    /**
     * Render assistant reply markdown as safe HTML for staff-facing chat.
     */
    public static function toHtml(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            return '';
        }

        return Str::markdown($content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
