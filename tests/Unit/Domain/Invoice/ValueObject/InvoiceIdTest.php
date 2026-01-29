<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Invoice\ValueObject;

use App\Domain\Invoice\ValueObject\InvoiceId;
use App\Domain\Shared\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class InvoiceIdTest extends TestCase
{
    public function testInvoiceIdExtendsUuid(): void
    {
        $invoiceId = InvoiceId::random();

        $this->assertInstanceOf(Uuid::class, $invoiceId);
        $this->assertInstanceOf(InvoiceId::class, $invoiceId);
    }

    public function testInvoiceIdFromString(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $invoiceId = InvoiceId::fromString($uuidString);

        $this->assertInstanceOf(InvoiceId::class, $invoiceId);
        $this->assertEquals($uuidString, $invoiceId->value());
    }

    public function testInvoiceIdRandom(): void
    {
        $invoiceId1 = InvoiceId::random();
        $invoiceId2 = InvoiceId::random();

        $this->assertInstanceOf(InvoiceId::class, $invoiceId1);
        $this->assertInstanceOf(InvoiceId::class, $invoiceId2);
        $this->assertNotEquals($invoiceId1->value(), $invoiceId2->value());
    }
}
