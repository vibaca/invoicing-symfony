<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared\ValueObject;

use App\Domain\Shared\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testCreateMoney(): void
    {
        $money = Money::fromFloat(100.50);

        $this->assertEquals(100.50, $money->amount());
        $this->assertEquals('USD', $money->currency());
    }

    public function testCreateZeroMoney(): void
    {
        $money = Money::zero();

        $this->assertEquals(0.0, $money->amount());
    }

    public function testAddMoney(): void
    {
        $money1 = Money::fromFloat(100.0);
        $money2 = Money::fromFloat(50.0);

        $result = $money1->add($money2);

        $this->assertEquals(150.0, $result->amount());
    }

    public function testSubtractMoney(): void
    {
        $money1 = Money::fromFloat(100.0);
        $money2 = Money::fromFloat(30.0);

        $result = $money1->subtract($money2);

        $this->assertEquals(70.0, $result->amount());
    }

    public function testMultiplyMoney(): void
    {
        $money = Money::fromFloat(25.0);

        $result = $money->multiply(4);

        $this->assertEquals(100.0, $result->amount());
    }

    public function testCannotAddDifferentCurrencies(): void
    {
        $usd = Money::fromFloat(100.0, 'USD');
        $eur = Money::fromFloat(100.0, 'EUR');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot add money with different currencies');

        $usd->add($eur);
    }

    public function testCannotCreateNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        Money::fromFloat(-10.0);
    }
}
