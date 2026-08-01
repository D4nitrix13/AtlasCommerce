<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Money::class)]
#[Group('support')]
final class MoneyTest extends TestCase
{
    #[Test]
    public function stores_cents_verbatim(): void
    {
        $money = new Money(cents: 1234, scale: 2);

        $this->assertSame(1234, $money->cents);
        $this->assertSame(2, $money->scale);
    }

    #[Test]
    public function from_decimal_converts_to_cents_without_floating_point_loss(): void
    {
        $money = Money::fromDecimal('12.34', scale: 2);

        $this->assertSame(1234, $money->cents);
    }

    #[Test]
    public function from_decimal_handles_negative_values(): void
    {
        $money = Money::fromDecimal('-7.50', scale: 2);

        $this->assertSame(-750, $money->cents);
        $this->assertTrue($money->isNegative());
    }

    #[Test]
    public function from_decimal_rounds_consistently(): void
    {
        // 12.345 a 2 decimales → 1234 / 1235 (regla round-half-up).
        $a = Money::fromDecimal('12.345', scale: 2);
        $b = Money::fromDecimal('12.344', scale: 2);

        $this->assertSame(1235, $a->cents);
        $this->assertSame(1234, $b->cents);
    }

    #[Test]
    public function to_decimal_formats_with_the_correct_scale(): void
    {
        $money = new Money(cents: 1500, scale: 2);

        $this->assertSame('15.00', $money->toDecimal());
    }

    #[Test]
    public function to_decimal_handles_zero_scale(): void
    {
        $money = new Money(cents: 1500, scale: 0);

        $this->assertSame('1500', $money->toDecimal());
    }

    #[Test]
    public function add_preserves_scale(): void
    {
        $a = new Money(cents: 1000, scale: 2);
        $b = new Money(cents: 250, scale: 2);

        $this->assertSame(1250, $a->add($b)->cents);
    }

    #[Test]
    public function add_rejects_different_scales(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $a = new Money(cents: 1000, scale: 2);
        $b = new Money(cents: 250, scale: 3);

        $a->add($b);
    }

    #[Test]
    public function split_distributes_without_pennies_loss(): void
    {
        $money = new Money(cents: 1001, scale: 2);

        $parts = $money->split(3);

        $this->assertCount(3, $parts);
        $this->assertSame(1001, array_sum(array_map(fn ($p) => $p->cents, $parts)));

        // La base es intdiv(1001,3)=333; remainder=1001-(333*3)=2.
        // El último parte absorbe el remanente para que la suma cierre.
        $this->assertSame(333, $parts[0]->cents);
        $this->assertSame(333, $parts[1]->cents);
        $this->assertSame(335, $parts[2]->cents);
    }

    #[Test]
    public function multiply_by_int_is_exact(): void
    {
        $money = new Money(cents: 123, scale: 2);

        $this->assertSame(369, $money->multiplyBy(3)->cents);
    }

    #[Test]
    public function cents_remain_in_int_range(): void
    {
        $money = new Money(cents: PHP_INT_MAX, scale: 0);

        $this->assertSame(PHP_INT_MAX, $money->cents);
    }

    #[Test]
    public function invalid_scale_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money(cents: 100, scale: 7);
    }
}
