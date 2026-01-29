<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Invoice;

use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Invoice\ValueObject\InvoiceId;
use App\Domain\Invoice\ValueObject\InvoiceNumber;
use App\Domain\Shared\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    public function testCreateInvoice(): void
    {
        $invoiceId = InvoiceId::random();
        $invoiceNumber = InvoiceNumber::fromString('INV-2025-001');
        $customerId = 'customer-123';
        $issueDate = new \DateTimeImmutable('2025-01-25');
        $dueDate = new \DateTimeImmutable('2025-02-25');

        $invoice = Invoice::create(
            $invoiceId,
            $invoiceNumber,
            $customerId,
            $issueDate,
            $dueDate
        );

        $this->assertEquals($invoiceId->value(), $invoice->id()->value());
        $this->assertEquals($invoiceNumber->value(), $invoice->number()->value());
        $this->assertEquals($customerId, $invoice->customerId());
        $this->assertTrue($invoice->status()->isDraft());
        $this->assertEquals(0.0, $invoice->totalAmount()->amount());
        $this->assertEquals($issueDate, $invoice->issueDate());
        $this->assertEquals($dueDate, $invoice->dueDate());
        $this->assertInstanceOf(\DateTimeImmutable::class, $invoice->createdAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $invoice->updatedAt());
    }

    public function testAddItemToInvoice(): void
    {
        $invoice = $this->createInvoice();
        $item = new InvoiceItem(
            'product-123',
            'Test Product',
            2,
            Money::fromFloat(99.99)
        );

        $invoice->addItem($item);

        $this->assertCount(1, $invoice->items());
        $this->assertEquals(199.98, $invoice->totalAmount()->amount());
    }

    public function testMarkInvoiceAsIssued(): void
    {
        $invoice = $this->createInvoice();
        $item = new InvoiceItem(
            'product-123',
            'Test Product',
            1,
            Money::fromFloat(100.0)
        );
        $invoice->addItem($item);

        $invoice->markAsIssued();

        $this->assertTrue($invoice->status()->isIssued());
    }

    public function testCannotIssueInvoiceWithoutItems(): void
    {
        $invoice = $this->createInvoice();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot issue invoice without items');

        $invoice->markAsIssued();
    }

    public function testMarkInvoiceAsPaid(): void
    {
        $invoice = $this->createInvoice();
        $item = new InvoiceItem(
            'product-123',
            'Test Product',
            1,
            Money::fromFloat(100.0)
        );
        $invoice->addItem($item);
        $invoice->markAsIssued();

        $invoice->markAsPaid();

        $this->assertTrue($invoice->status()->isPaid());
    }

    public function testCannotMarkAsPaidWithoutIssuing(): void
    {
        $invoice = $this->createInvoice();
        $item = new InvoiceItem(
            'product-123',
            'Test Product',
            1,
            Money::fromFloat(100.0)
        );
        $invoice->addItem($item);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invoice must be issued before marking as paid');

        $invoice->markAsPaid();
    }

    public function testRemoveItemFromDraftInvoice(): void
    {
        $invoice = $this->createInvoice();
        $invoice->addItem(new InvoiceItem('product-1', 'Product 1', 2, Money::fromFloat(99.99)));
        $invoice->addItem(new InvoiceItem('product-2', 'Product 2', 1, Money::fromFloat(50.0)));

        $this->assertCount(2, $invoice->items());
        $this->assertEqualsWithDelta(249.98, $invoice->totalAmount()->amount(), 0.01);

        $invoice->removeItem(0);

        $this->assertCount(1, $invoice->items());
        $this->assertEquals('product-2', $invoice->items()[0]->productId());
        $this->assertEqualsWithDelta(50.0, $invoice->totalAmount()->amount(), 0.01);
    }

    public function testCannotRemoveItemFromIssuedInvoice(): void
    {
        $invoice = $this->createInvoice();
        $invoice->addItem(new InvoiceItem('product-123', 'Test', 1, Money::fromFloat(100.0)));
        $invoice->markAsIssued();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Can only remove items from draft invoices');

        $invoice->removeItem(0);
    }

    public function testRemoveItemInvalidIndexThrows(): void
    {
        $invoice = $this->createInvoice();
        $invoice->addItem(new InvoiceItem('product-123', 'Test', 1, Money::fromFloat(100.0)));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Item index out of bounds');

        $invoice->removeItem(1);
    }

    public function testUpdateItemQuantityInDraftInvoice(): void
    {
        $invoice = $this->createInvoice();
        $invoice->addItem(new InvoiceItem('product-123', 'Test', 2, Money::fromFloat(99.99)));

        $this->assertCount(1, $invoice->items());
        $this->assertEquals(2, $invoice->items()[0]->quantity());
        $this->assertEqualsWithDelta(199.98, $invoice->totalAmount()->amount(), 0.01);

        $invoice->updateItemQuantity(0, 5);

        $this->assertEquals(5, $invoice->items()[0]->quantity());
        $this->assertEqualsWithDelta(499.95, $invoice->totalAmount()->amount(), 0.01);
    }

    public function testCannotUpdateItemQuantityFromIssuedInvoice(): void
    {
        $invoice = $this->createInvoice();
        $invoice->addItem(new InvoiceItem('product-123', 'Test', 1, Money::fromFloat(100.0)));
        $invoice->markAsIssued();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Can only update item quantity in draft invoices');

        $invoice->updateItemQuantity(0, 3);
    }

    public function testUpdateItemQuantityInvalidIndexThrows(): void
    {
        $invoice = $this->createInvoice();
        $invoice->addItem(new InvoiceItem('product-123', 'Test', 1, Money::fromFloat(100.0)));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Item index out of bounds');

        $invoice->updateItemQuantity(1, 2);
    }

    public function testUpdateItemQuantityInvalidQuantityThrows(): void
    {
        $invoice = $this->createInvoice();
        $invoice->addItem(new InvoiceItem('product-123', 'Test', 1, Money::fromFloat(100.0)));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Quantity must be greater than zero');

        $invoice->updateItemQuantity(0, 0);
    }

    private function createInvoice(): Invoice
    {
        return Invoice::create(
            InvoiceId::random(),
            InvoiceNumber::fromString('INV-2025-001'),
            'customer-123',
            new \DateTimeImmutable('2025-01-25'),
            new \DateTimeImmutable('2025-02-25')
        );
    }
}
