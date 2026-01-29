<?php

declare(strict_types=1);

namespace App\Infrastructure\EventBus;

use App\Domain\Shared\Event\DomainEvent;
use App\Domain\Shared\Event\EventBus;
use Symfony\Component\Messenger\MessageBusInterface;

final class RabbitMQEventBus implements EventBus
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->messageBus->dispatch($event);
        }
    }
}
