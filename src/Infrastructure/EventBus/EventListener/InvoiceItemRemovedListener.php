<?php

declare(strict_types=1);

namespace App\Infrastructure\EventBus\EventListener;

use App\Domain\Invoice\Event\InvoiceItemRemoved;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class InvoiceItemRemovedListener
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(InvoiceItemRemoved $event): void
    {
        $this->logger->info('Invoice item removed', [
            'invoiceId' => $event->invoiceId(),
            'productId' => $event->productId(),
            'quantity' => $event->quantity(),
            'unitPrice' => $event->unitPrice(),
        ]);
    }
}
