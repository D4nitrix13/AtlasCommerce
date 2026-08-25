<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Models\Auth\User;
use App\Services\Auth\RecoveryCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Unit tests para RecoveryCodeService.
 */
final class RecoveryCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecoveryCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecoveryCodeService;
    }

    public function test_generate_creates_default_count_codes(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor(user: $user);

        $this->assertCount(expectedCount: RecoveryCodeService::DEFAULT_COUNT, haystack: $codes);
        $this->assertSame(
            expected: RecoveryCodeService::DEFAULT_COUNT,
            actual: $user->recoveryCodes()->count(),
        );
    }

    public function test_generate_creates_custom_count(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor(user: $user, count: 5);

        $this->assertCount(expectedCount: 5, haystack: $codes);
    }

    public function test_generate_rejects_invalid_count(): void
    {
        $user = User::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        $this->service->generateFor(user: $user, count: 0);
    }

    public function test_generate_returns_unique_plain_codes(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor(user: $user, count: 20);

        $this->assertCount(expectedCount: 20, haystack: array_unique($codes));
    }

    public function test_generated_codes_meet_format(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor(user: $user, count: 20);

        foreach ($codes as $code) {
            // Sin ambigüedades: 0/O, 1/I/L excluidos
            $this->assertDoesNotMatchRegularExpression('/[0O1IL]/', $code);
            $this->assertSame(
                expected: RecoveryCodeService::CODE_LENGTH,
                actual: strlen($code),
            );
            $this->assertMatchesRegularExpression('/^[A-Z2-9]+$/', $code);
        }
    }

    public function test_generate_invalidates_previous_codes(): void
    {
        $user = User::factory()->create();
        $first = $this->service->generateFor(user: $user, count: 5);
        $second = $this->service->generateFor(user: $user, count: 5);

        $this->assertNotSame(expected: $first, actual: $second);
        $this->assertSame(expected: 5, actual: $user->recoveryCodes()->count());

        // Los primeros codes ya no deben ser válidos
        foreach ($first as $oldCode) {
            $this->assertFalse(
                condition: $this->service->verifyAndConsume(user: $user, code: $oldCode),
            );
        }
    }

    public function test_verify_and_consume_valid_code(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor(user: $user, count: 3);
        $code = $codes[0];

        $this->assertTrue(
            condition: $this->service->verifyAndConsume(user: $user, code: $code),
        );
    }

    public function test_verify_and_consume_marks_used(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor(user: $user, count: 3);
        $code = $codes[0];

        $this->service->verifyAndConsume(user: $user, code: $code);

        $row = $user->recoveryCodes()
            ->where(column: 'id', operator: '=', value: $user->recoveryCodes()->first()->id)
            ->first();
        $this->assertNotNull($row->used_at);
    }

    public function test_cannot_consume_same_code_twice(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor(user: $user, count: 3);
        $code = $codes[0];

        $this->assertTrue(condition: $this->service->verifyAndConsume(user: $user, code: $code));
        $this->assertFalse(condition: $this->service->verifyAndConsume(user: $user, code: $code));
    }

    public function test_invalid_code_returns_false(): void
    {
        $user = User::factory()->create();
        $this->service->generateFor(user: $user, count: 3);

        $this->assertFalse(
            condition: $this->service->verifyAndConsume(user: $user, code: 'AAAAAAAAAA'),
        );
    }

    public function test_normalizes_user_input(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor(user: $user, count: 3);
        $code = $codes[0];

        // El user puede tipear con lowercase o con espacios
        $this->assertTrue(condition: $this->service->verifyAndConsume(
            user: $user,
            code: strtolower($code),
        ));
    }

    public function test_available_count_decreases_after_consumption(): void
    {
        $user = User::factory()->create();
        $this->service->generateFor(user: $user, count: 5);
        $this->assertSame(expected: 5, actual: $this->service->availableCount(user: $user));

        $codes = $this->service->generateFor(user: $user, count: 5); // regenera, no consume
        $this->service->verifyAndConsume(user: $user, code: $codes[0]);
        $this->assertSame(expected: 4, actual: $this->service->availableCount(user: $user));
    }
}
