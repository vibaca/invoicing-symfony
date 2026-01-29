<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

use App\Domain\Invoice\Event\InvoiceCreated;
use App\Domain\Invoice\Event\InvoiceItemAdded;
use App\Domain\Invoice\Event\InvoiceItemQuantityUpdated;
use App\Domain\Invoice\Event\InvoiceItemRemoved;
use App\Domain\Invoice\ValueObject\InvoiceId;
use App\Domain\Invoice\ValueObject\InvoiceNumber;
use App\Domain\Invoice\ValueObject\InvoiceStatus;
use App\Domain\Shared\Event\DomainEvent;
use App\Domain\Shared\ValueObject\Money;
use DateTimeImmutable;

final class Invoice
{
    private InvoiceId $id;
    private InvoiceNumber $number;
    private string $customerId;
    private InvoiceStatus $status;
    private DateTimeImmutable $issueDate;
    private DateTimeImmutable $dueDate;
    private array $items;
    private Money $totalAmount;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    /** @var DomainEvent[] */
    private array $domainEvents = [];

    private function __construct(
        InvoiceId $id,
        InvoiceNumber $number,
        string $customerId,
        DateTimeImmutable $issueDate,
        DateTimeImmutable $dueDate
    ) {
        $this->id = $id;
        $this->number = $number;
        $this->customerId = $customerId;
        $this->status = InvoiceStatus::draft();
        $this->issueDate = $issueDate;
        $this->dueDate = $dueDate;
        $this->items = [];
        $this->totalAmount = Money::zero();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public static function create(
        InvoiceId $id,
        InvoiceNumber $number,
        string $customerId,
        DateTimeImmutable $issueDate,
        DateTimeImmutable $dueDate
    ): self {
        $invoice = new self($id, $number, $customerId, $issueDate, $dueDate);

        $invoice->recordEvent(new InvoiceCreated(
            $id->value(),
            $number->value(),
            $customerId
        ));

        return $invoice;
    }

    public function addItem(InvoiceItem $item): void
    {
        $this->items[] = $item;
        $this->recalculateTotal();
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new InvoiceItemAdded(
            $this->id->value(),
            $item->productId(),
            $item->quantity(),
            $item->unitPrice()->amount()
        ));
    }

    public function removeItem(int $index): void
    {
        if (! $this->status->isDraft()) {
            throw new \DomainException('Can only remove items from draft invoices');
        }

        if ($index < 0 || $index >= \count($this->items)) {
            throw new \DomainException('Item index out of bounds: ' . $index);
        }

        $removed = $this->items[$index];
        array_splice($this->items, $index, 1);
        $this->recalculateTotal();
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new InvoiceItemRemoved(
            $this->id->value(),
            $removed->productId(),
            $removed->quantity(),
            $removed->unitPrice()->amount()
        ));
    }

    public function updateItemQuantity(int $index, int $newQuantity): void
    {
        if (! $this->status->isDraft()) {
            throw new \DomainException('Can only update item quantity in draft invoices');
        }

        if ($index < 0 || $index >= \count($this->items)) {
            throw new \DomainException('Item index out of bounds: ' . $index);
        }

        if ($newQuantity <= 0) {
            throw new \DomainException('Quantity must be greater than zero');
        }

        $old = $this->items[$index];
        $this->items[$index] = new InvoiceItem(
            $old->productId(),
            $old->description(),
            $newQuantity,
            $old->unitPrice()
        );
        $this->recalculateTotal();
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new InvoiceItemQuantityUpdated(
            $this->id->value(),
            $old->productId(),
            $old->quantity(),
            $newQuantity,
            $old->unitPrice()->amount()
        ));
    }

    public function markAsIssued(): void
    {
        if ($this->status->isIssued()) {
            throw new \DomainException('Invoice is already issued');
        }

        if (empty($this->items)) {
            throw new \DomainException('Cannot issue invoice without items');
        }

        $this->status = InvoiceStatus::issued();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markAsPaid(): void
    {
        if (! $this->status->isIssued()) {
            throw new \DomainException('Invoice must be issued before marking as paid');
        }

        $this->status = InvoiceStatus::paid();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): InvoiceId
    {
        return $this->id;
    }

    public function number(): InvoiceNumber
    {
        return $this->number;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function status(): InvoiceStatus
    {
        return $this->status;
    }

    public function totalAmount(): Money
    {
        return $this->totalAmount;
    }

    public function items(): array
    {
        return $this->items;
    }

    public function issueDate(): DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function dueDate(): DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function recalculateTotal(): void
    {
        $total = Money::zero();
        foreach ($this->items as $item) {
            $total = $total->add($item->totalPrice());
        }
        $this->totalAmount = $total;
    }

    private function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
}
