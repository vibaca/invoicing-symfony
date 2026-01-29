<?php

declare(strict_types=1);

namespace App\Application\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class RemoveInvoiceItemCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'Invoice ID is required')]
        public readonly string $invoiceId,
        #[Assert\Range(min: 0, minMessage: 'Item index must be greater than or equal to zero')]
        public readonly int $itemIndex
    ) {
    }
}
