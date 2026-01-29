<?php

declare(strict_types=1);

namespace App\Application\Command;

final class IssueInvoiceCommand
{
    public function __construct(
        public readonly string $invoiceId
    ) {
    }
}
