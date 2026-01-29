<?php

declare(strict_types=1);

namespace App\Domain\Invoice\ValueObject;

final class InvoiceNumber
{
    private string $value;

    private function __construct(string $value)
    {
        if (empty(trim($value))) {
            throw new \InvalidArgumentException('Invoice number cannot be empty');
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
