<?php

declare(strict_types=1);

namespace App\Application\Query\ViewModel;

final class InvoiceListViewModel
{
    public function __construct(
        public readonly array $invoices,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit
    ) {
    }

    public function totalPages(): int
    {
        return (int) ceil($this->total / $this->limit);
    }
}
