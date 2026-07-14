<?php

namespace Modules\AI\Classes\Services;

class PhiRedactionService
{
    /**
     * @var array<string, string>
     */
    protected array $tokenMap = [];

    protected int $patientCounter = 0;

    protected int $mrnCounter = 0;

    public function redact(string $text): string
    {
        if (! config('ai-assistant.phi_redaction.enabled', true)) {
            return $text;
        }

        $prefix = (string) config('ai-assistant.phi_redaction.token_prefix', 'REF');

        $patterns = [
            '/\b[A-Z]{2,3}\d{4,10}\b/' => fn (): string => $this->token("{$prefix}_MRN", 'mrn'),
            '/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/' => fn (): string => $this->token("{$prefix}_PHONE", 'phone'),
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/' => fn (): string => $this->token("{$prefix}_EMAIL", 'email'),
        ];

        foreach ($patterns as $pattern => $tokenFactory) {
            $text = preg_replace_callback($pattern, function (array $matches) use ($tokenFactory): string {
                $value = $matches[0];
                if (isset($this->tokenMap[$value])) {
                    return $this->tokenMap[$value];
                }

                $token = $tokenFactory();
                $this->tokenMap[$value] = $token;

                return $token;
            }, $text) ?? $text;
        }

        return $text;
    }

    public function patientToken(string $patientId): string
    {
        $prefix = (string) config('ai-assistant.phi_redaction.token_prefix', 'REF');

        return $this->token("{$prefix}_PATIENT", 'patient', $patientId);
    }

    /**
     * @return array<string, string>
     */
    public function tokenMap(): array
    {
        return $this->tokenMap;
    }

    protected function token(string $base, string $type, ?string $stableId = null): string
    {
        if ($stableId !== null) {
            $key = "{$type}:{$stableId}";
            if (isset($this->tokenMap[$key])) {
                return $this->tokenMap[$key];
            }

            $this->patientCounter++;
            $token = "{$base}_{$this->patientCounter}";
            $this->tokenMap[$key] = $token;

            return $token;
        }

        return match ($type) {
            'mrn' => "{$base}_".(++$this->mrnCounter),
            'phone' => "{$base}_PHONE",
            'email' => "{$base}_EMAIL",
            default => "{$base}_".(++$this->patientCounter),
        };
    }
}
