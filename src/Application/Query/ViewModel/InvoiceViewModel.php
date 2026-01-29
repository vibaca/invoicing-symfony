<?php

declare(strict_types=1);

namespace App\Application\Query\ViewModel;

use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceItem;

final class InvoiceViewModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $number,
        public readonly string $customerId,
        public readonly string $status,
        public readonly string $issueDate,
        public readonly string $dueDate,
        public readonly float $totalAmount,
        public readonly string $currency,
        public readonly array $items,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {
    }

    public static function fromDomain(Invoice $invoice): self
    {
        $items = array_map(
            fn(InvoiceItem $item) => [
                'productId' => $item->productId(),
                'description' => $item->description(),
                'quantity' => $item->quantity(),
                'unitPrice' => $item->unitPrice()->amount(),
                'totalPrice' => $item->totalPrice()->amount(),
                'currency' => $item->unitPrice()->currency(),
            ],
            $invoice->items()
        );

        return new self(
            $invoice->id()->value(),
            $invoice->number()->value(),
            $invoice->customerId(),
            $invoice->status()->value(),
            $invoice->issueDate()->format('Y-m-d'),
            $invoice->dueDate()->format('Y-m-d'),
            $invoice->totalAmount()->amount(),
            $invoice->totalAmount()->currency(),
            $items,
            $invoice->createdAt()->format('Y-m-d H:i:s'),
            $invoice->updatedAt()->format('Y-m-d H:i:s')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customerId' => $this->customerId,
            'status' => $this->status,
            'issueDate' => $this->issueDate,
            'dueDate' => $this->dueDate,
            'totalAmount' => $this->totalAmount,
            'currency' => $this->currency,
            'items' => $this->items,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
