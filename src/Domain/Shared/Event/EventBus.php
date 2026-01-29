<?php

declare(strict_types=1);

namespace App\Domain\Shared\Event;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
