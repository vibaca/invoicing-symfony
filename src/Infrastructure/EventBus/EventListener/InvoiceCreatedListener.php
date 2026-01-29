<?php

declare(strict_types=1);

namespace App\Infrastructure\EventBus\EventListener;

use App\Domain\Invoice\Event\InvoiceCreated;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class InvoiceCreatedListener
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(InvoiceCreated $event): void
    {
        $this->logger->info('Invoice created', [
            'invoiceId' => $event->invoiceId(),
            'invoiceNumber' => $event->invoiceNumber(),
            'customerId' => $event->customerId(),
        ]);

        // Here you could add additional event handling logic
        // e.g., send notifications, update read models, etc.
    }
}
