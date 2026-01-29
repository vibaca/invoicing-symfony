<?php

declare(strict_types=1);

namespace App\Application\Command\Handler;

use App\Application\Command\IssueInvoiceCommand;
use App\Domain\Invoice\Repository\InvoiceRepository;
use App\Domain\Invoice\ValueObject\InvoiceId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class IssueInvoiceCommandHandler
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository
    ) {
    }

    public function __invoke(IssueInvoiceCommand $command): void
    {
        $invoiceId = InvoiceId::fromString($command->invoiceId);
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if ($invoice === null) {
            throw new \DomainException('Invoice not found');
        }

        $invoice->markAsIssued();
        $this->invoiceRepository->save($invoice);
    }
}
