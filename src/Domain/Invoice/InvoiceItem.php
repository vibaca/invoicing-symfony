<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

use App\Domain\Shared\ValueObject\Money;

final class InvoiceItem
{
    private string $productId;
    private string $description;
    private int $quantity;
    private Money $unitPrice;

    public function __construct(
        string $productId,
        string $description,
        int $quantity,
        Money $unitPrice
    ) {
        if ($quantity <= 0) {
            throw new \DomainException('Quantity must be greater than zero');
        }

        if ($unitPrice->amount() < 0) {
            throw new \DomainException('Unit price cannot be negative');
        }

        $this->productId = $productId;
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function totalPrice(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
