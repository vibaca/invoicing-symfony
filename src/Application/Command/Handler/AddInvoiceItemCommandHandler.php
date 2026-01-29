<?php

declare(strict_types=1);

namespace App\Application\Command\Handler;

use App\Application\Command\AddInvoiceItemCommand;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Invoice\Repository\InvoiceRepository;
use App\Domain\Invoice\ValueObject\InvoiceId;
use App\Domain\Shared\Event\EventBus;
use App\Domain\Shared\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AddInvoiceItemCommandHandler
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly EventBus $eventBus
    ) {
    }

    public function __invoke(AddInvoiceItemCommand $command): void
    {
        $invoiceId = InvoiceId::fromString($command->invoiceId);
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if ($invoice === null) {
            throw new \DomainException('Invoice not found');
        }

        $item = new InvoiceItem(
            $command->productId,
            $command->description,
            $command->quantity,
            Money::fromFloat($command->unitPrice)
        );

        $invoice->addItem($item);
        $this->invoiceRepository->save($invoice);

        // Publish domain events
        $events = $invoice->pullDomainEvents();
        $this->eventBus->publish(...$events);
    }
}
