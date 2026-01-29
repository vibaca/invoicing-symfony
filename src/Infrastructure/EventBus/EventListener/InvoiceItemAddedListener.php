<?php

declare(strict_types=1);

namespace App\Infrastructure\EventBus\EventListener;

use App\Domain\Invoice\Event\InvoiceItemAdded;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class InvoiceItemAddedListener
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(InvoiceItemAdded $event): void
    {
        $this->logger->info('Invoice item added', [
            'invoiceId' => $event->invoiceId(),
            'productId' => $event->productId(),
            'quantity' => $event->quantity(),
            'unitPrice' => $event->unitPrice(),
        ]);

        // Here you could add additional event handling logic
        // e.g., update inventory, calculate statistics, etc.
    }
}
