<?php

declare(strict_types=1);

namespace App\Application\Query;

final class GetInvoiceQuery
{
    public function __construct(
        public readonly string $invoiceId
    ) {
    }
}
