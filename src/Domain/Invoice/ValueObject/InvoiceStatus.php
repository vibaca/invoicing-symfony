<?php

declare(strict_types=1);

namespace App\Domain\Invoice\ValueObject;

final class InvoiceStatus
{
    private const DRAFT = 'draft';
    private const ISSUED = 'issued';
    private const PAID = 'paid';
    private const CANCELLED = 'cancelled';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
    }

    public static function issued(): self
    {
        return new self(self::ISSUED);
    }

    public static function paid(): self
    {
        return new self(self::PAID);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public function isDraft(): bool
    {
        return $this->value === self::DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->value === self::ISSUED;
    }

    public function isPaid(): bool
    {
        return $this->value === self::PAID;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
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
