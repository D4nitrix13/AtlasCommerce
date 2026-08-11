<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Models\Auth\RecoveryCode;
use App\Models\Auth\User;
use App\Services\Auth\RecoveryCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->service = new RecoveryCodeService();
    }

    public function test_generate_creates_default_count_codes(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor($user);

        $this->assertCount(RecoveryCodeService::DEFAULT_COUNT, $codes);
        $this->assertSame(RecoveryCodeService::DEFAULT_COUNT, $user->recoveryCodes()->count());
    }

    public function test_generate_creates_custom_count(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor($user, count: 5);

        $this->assertCount(5, $codes);
    }

    public function test_generate_rejects_invalid_count(): void
    {
        $user = User::factory()->create();
        $this->expectException(\InvalidArgumentException::class);
        $this->service->generateFor($user, count: 0);
    }

    public function test_generate_returns_unique_plain_codes(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor($user, count: 20);

        $this->assertCount(20, array_unique($codes));
    }

    public function test_generated_codes_meet_format(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor($user, count: 20);

        foreach ($codes as $code) {
            // Sin ambigüedades: 0/O, 1/I/L excluidos
            $this->assertDoesNotMatchRegularExpression('/[0O1IL]/', $code);
            $this->assertSame(RecoveryCodeService::CODE_LENGTH, strlen($code));
            $this->assertMatchesRegularExpression('/^[A-Z2-9]+$/', $code);
        }
    }

    public function test_generate_invalidates_previous_codes(): void
    {
        $user = User::factory()->create();
        $first = $this->service->generateFor($user, count: 5);
        $second = $this->service->generateFor($user, count: 5);

        $this->assertNotSame($first, $second);
        $this->assertSame(5, $user->recoveryCodes()->count());

        // Los primeros codes ya no deben ser válidos
        foreach ($first as $oldCode) {
            $this->assertFalse($this->service->verifyAndConsume($user, $oldCode));
        }
    }

    public function test_verify_and_consume_valid_code(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor($user, count: 3);
        $code = $codes[0];

        $this->assertTrue($this->service->verifyAndConsume($user, $code));
    }

    public function test_verify_and_consume_marks_used(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor($user, count: 3);
        $code = $codes[0];

        $this->service->verifyAndConsume($user, $code);

        $row = $user->recoveryCodes()->where(column: 'id', operator: '=', value: $user->recoveryCodes()->first()->id)->first();
        $this->assertNotNull($row->used_at);
    }

    public function test_cannot_consume_same_code_twice(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor($user, count: 3);
        $code = $codes[0];

        $this->assertTrue($this->service->verifyAndConsume($user, $code));
        $this->assertFalse($this->service->verifyAndConsume($user, $code));
    }

    public function test_invalid_code_returns_false(): void
    {
        $user = User::factory()->create();
        $this->service->generateFor($user, count: 3);

        $this->assertFalse($this->service->verifyAndConsume($user, 'AAAAAAAAAA'));
    }

    public function test_normalizes_user_input(): void
    {
        $user = User::factory()->create();
        $codes = $this->service->generateFor($user, count: 3);
        $code = $codes[0];

        // El user puede tipear con lowercase o con espacios
        $this->assertTrue($this->service->verifyAndConsume(
            $user,
            strtolower($code),
        ));
    }

    public function test_available_count_decreases_after_consumption(): void
    {
        $user = User::factory()->create();
        $this->service->generateFor($user, count: 5);
        $this->assertSame(5, $this->service->availableCount($user));

        $codes = $this->service->generateFor($user, count: 5); // regenera, no consume
        $this->service->verifyAndConsume($user, $codes[0]);
        $this->assertSame(4, $this->service->availableCount($user));
    }
}
