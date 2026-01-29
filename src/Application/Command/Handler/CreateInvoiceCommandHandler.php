<?php

declare(strict_types=1);

namespace App\Application\Command\Handler;

use App\Application\Command\CreateInvoiceCommand;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\Repository\InvoiceRepository;
use App\Domain\Invoice\ValueObject\InvoiceId;
use App\Domain\Invoice\ValueObject\InvoiceNumber;
use App\Domain\Shared\Event\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateInvoiceCommandHandler
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly EventBus $eventBus
    ) {
    }

    public function __invoke(CreateInvoiceCommand $command): void
    {
        $invoiceId = InvoiceId::fromString($command->invoiceId);
        $invoiceNumber = InvoiceNumber::fromString($command->invoiceNumber);

        // Check if invoice number already exists
        $existingInvoice = $this->invoiceRepository->findByNumber($command->invoiceNumber);
        if ($existingInvoice !== null) {
            throw new \DomainException('Invoice number already exists');
        }

        $invoice = Invoice::create(
            $invoiceId,
            $invoiceNumber,
            $command->customerId,
            $command->issueDate,
            $command->dueDate
        );

        $this->invoiceRepository->save($invoice);

        // Publish domain events
        $events = $invoice->pullDomainEvents();
        $this->eventBus->publish(...$events);
    }
}
