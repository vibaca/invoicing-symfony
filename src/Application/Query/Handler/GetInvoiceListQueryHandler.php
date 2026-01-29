<?php

declare(strict_types=1);

namespace App\Application\Query\Handler;

use App\Application\Query\GetInvoiceListQuery;
use App\Application\Query\ViewModel\InvoiceListViewModel;
use App\Application\Query\ViewModel\InvoiceViewModel;
use App\Domain\Invoice\Repository\InvoiceRepository;

final class GetInvoiceListQueryHandler
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository
    ) {
    }

    public function handle(GetInvoiceListQuery $query): InvoiceListViewModel
    {
        $invoices = $this->invoiceRepository->findAll();
        
        $total = count($invoices);
        $page = $query->page ?? 1;
        $limit = $query->limit ?? 10;
        $offset = ($page - 1) * $limit;
        
        $paginatedInvoices = array_slice($invoices, $offset, $limit);
        
        $viewModels = array_map(
            fn($invoice) => InvoiceViewModel::fromDomain($invoice),
            $paginatedInvoices
        );

        return new InvoiceListViewModel(
            $viewModels,
            $total,
            $page,
            $limit
        );
    }
}
