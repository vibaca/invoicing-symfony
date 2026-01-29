<?php

declare(strict_types=1);

namespace App\Infrastructure\EventBus\EventListener;

use App\Domain\Invoice\Event\InvoiceItemQuantityUpdated;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class InvoiceItemQuantityUpdatedListener
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(InvoiceItemQuantityUpdated $event): void
    {
        $this->logger->info('Invoice item quantity updated', [
            'invoiceId' => $event->invoiceId(),
            'productId' => $event->productId(),
            'oldQuantity' => $event->oldQuantity(),
            'newQuantity' => $event->newQuantity(),
            'unitPrice' => $event->unitPrice(),
        ]);
    }
}
