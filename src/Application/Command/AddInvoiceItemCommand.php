<?php

declare(strict_types=1);

namespace App\Application\Command;

final class AddInvoiceItemCommand
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly string $productId,
        public readonly string $description,
        public readonly int $quantity,
        public readonly float $unitPrice
    ) {
    }
}
