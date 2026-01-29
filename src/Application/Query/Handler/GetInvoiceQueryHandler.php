<?php

declare(strict_types=1);

namespace App\Application\Query\Handler;

use App\Application\Query\GetInvoiceQuery;
use App\Application\Query\ViewModel\InvoiceViewModel;
use App\Domain\Invoice\Repository\InvoiceRepository;
use App\Domain\Invoice\ValueObject\InvoiceId;

final class GetInvoiceQueryHandler
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository
    ) {
    }

    public function handle(GetInvoiceQuery $query): ?InvoiceViewModel
    {
        $invoiceId = InvoiceId::fromString($query->invoiceId);
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if ($invoice === null) {
            return null;
        }

        return InvoiceViewModel::fromDomain($invoice);
    }
}
