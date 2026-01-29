<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Repository;

use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\ValueObject\InvoiceId;

interface InvoiceRepository
{
    public function save(Invoice $invoice): void;
    public function findById(InvoiceId $id): ?Invoice;
    public function findByNumber(string $number): ?Invoice;
    public function findAll(): array;
}
