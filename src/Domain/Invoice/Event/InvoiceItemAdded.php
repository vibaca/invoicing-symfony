<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Event;

use App\Domain\Shared\Event\DomainEvent;
use DateTimeImmutable;

final class InvoiceItemAdded implements DomainEvent
{
    private string $invoiceId;
    private string $productId;
    private int $quantity;
    private float $unitPrice;
    private DateTimeImmutable $occurredOn;

    public function __construct(
        string $invoiceId,
        string $productId,
        int $quantity,
        float $unitPrice
    ) {
        $this->invoiceId = $invoiceId;
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->occurredOn = new DateTimeImmutable();
    }

    public function invoiceId(): string
    {
        return $this->invoiceId;
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): float
    {
        return $this->unitPrice;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'invoice.item_added';
    }
}
