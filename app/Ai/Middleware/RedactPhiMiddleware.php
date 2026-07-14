<?php

namespace Modules\AI\Ai\Middleware;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;
use Modules\AI\Classes\Services\PhiRedactionService;

class RedactPhiMiddleware
{
    public function __construct(
        protected PhiRedactionService $redactor,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next)
    {
        return $next($prompt->revise($this->redactor->redact($prompt->prompt)));
    }
}
