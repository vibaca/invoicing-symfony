<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Event;

use App\Domain\Shared\Event\DomainEvent;
use DateTimeImmutable;

final class InvoiceItemRemoved implements DomainEvent
{
    public function __construct(
        private readonly string $invoiceId,
        private readonly string $productId,
        private readonly int $quantity,
        private readonly float $unitPrice,
        private readonly DateTimeImmutable $occurredOn = new DateTimeImmutable()
    ) {
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
        return 'invoice.item_removed';
    }
}
