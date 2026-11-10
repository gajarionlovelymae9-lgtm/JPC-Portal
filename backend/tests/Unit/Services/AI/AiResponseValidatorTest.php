<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AiResponseValidator;
use Tests\TestCase;

class AiResponseValidatorTest extends TestCase
{
    private AiResponseValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AiResponseValidator;
    }

    public function test_validate_returns_valid_passthrough_for_grounded_response(): void
    {
        $result = $this->validator->validate('The completion rate is 80%.', ['completion_rate' => 80.0]);

        $this->assertTrue($result['valid']);
        $this->assertSame('The completion rate is 80%.', $result['sanitized']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_validate_always_returns_structured_envelope(): void
    {
        $result = $this->validator->validate('', []);

        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('sanitized', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertIsArray($result['warnings']);
    }
}
