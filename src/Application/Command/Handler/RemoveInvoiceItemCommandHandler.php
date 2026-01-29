<?php

declare(strict_types=1);

namespace App\Application\Command\Handler;

use App\Application\Command\RemoveInvoiceItemCommand;
use App\Domain\Invoice\Repository\InvoiceRepository;
use App\Domain\Invoice\ValueObject\InvoiceId;
use App\Domain\Shared\Event\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RemoveInvoiceItemCommandHandler
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly EventBus $eventBus
    ) {
    }

    public function __invoke(RemoveInvoiceItemCommand $command): void
    {
        $invoiceId = InvoiceId::fromString($command->invoiceId);
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if ($invoice === null) {
            throw new \DomainException('Invoice not found: ' . $command->invoiceId);
        }

        $invoice->removeItem($command->itemIndex);
        $this->invoiceRepository->save($invoice);

        foreach ($invoice->pullDomainEvents() as $event) {
            $this->eventBus->publish($event);
        }
    }
}
