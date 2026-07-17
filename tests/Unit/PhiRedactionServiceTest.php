<?php

namespace Modules\AI\Tests\Unit;

use Modules\AI\Classes\Services\PhiRedactionService;

class PhiRedactionServiceTest extends AITestCase
{
    public function test_redacts_email_phone_and_mrn_patterns(): void
    {
        $service = app(PhiRedactionService::class);

        $redacted = $service->redact('Patient MRN GH12345678 email jane@example.com phone 024-123-4567');

        $this->assertStringNotContainsString('jane@example.com', $redacted);
        $this->assertStringNotContainsString('024-123-4567', $redacted);
        $this->assertStringContainsString('REF_', $redacted);
    }

    public function test_patient_token_is_stable_for_same_id(): void
    {
        $service = app(PhiRedactionService::class);

        $first = $service->patientToken('patient-1');
        $second = $service->patientToken('patient-1');

        $this->assertSame($first, $second);
    }
}
