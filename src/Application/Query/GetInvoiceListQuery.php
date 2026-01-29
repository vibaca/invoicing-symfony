<?php

declare(strict_types=1);

namespace App\Application\Query;

final class GetInvoiceListQuery
{
    public function __construct(
        public readonly ?int $page = 1,
        public readonly ?int $limit = 10
    ) {
    }
}
