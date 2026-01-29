<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

class Uuid
{
    protected UuidInterface $value;

    protected function __construct(UuidInterface $value)
    {
        $this->value = $value;
    }

    /**
     * @return static
     */
    public static function fromString(string $value): static
    {
        if (!RamseyUuid::isValid($value)) {
            throw new InvalidArgumentException(sprintf('Invalid UUID: %s', $value));
        }

        // @phpstan-ignore-next-line - Safe because child classes don't override constructor
        return new static(RamseyUuid::fromString($value));
    }

    /**
     * @return static
     */
    public static function random(): static
    {
        // @phpstan-ignore-next-line - Safe because child classes don't override constructor
        return new static(RamseyUuid::uuid4());
    }

    public function value(): string
    {
        return $this->value->toString();
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return $this->value->toString();
    }
}
