<?php

declare(strict_types=1);

namespace App\Application\Command;

use DateTimeImmutable;

final class CreateInvoiceCommand
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly string $invoiceNumber,
        public readonly string $customerId,
        public readonly DateTimeImmutable $issueDate,
        public readonly DateTimeImmutable $dueDate
    ) {
    }
}
