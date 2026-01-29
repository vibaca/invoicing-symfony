<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Event;

use App\Domain\Shared\Event\DomainEvent;
use DateTimeImmutable;

final class InvoiceCreated implements DomainEvent
{
    private string $invoiceId;
    private string $invoiceNumber;
    private string $customerId;
    private DateTimeImmutable $occurredOn;

    public function __construct(
        string $invoiceId,
        string $invoiceNumber,
        string $customerId
    ) {
        $this->invoiceId = $invoiceId;
        $this->invoiceNumber = $invoiceNumber;
        $this->customerId = $customerId;
        $this->occurredOn = new DateTimeImmutable();
    }

    public function invoiceId(): string
    {
        return $this->invoiceId;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'invoice.created';
    }
}
