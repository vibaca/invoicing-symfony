<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Invoice\ValueObject\InvoiceId;
use App\Domain\Invoice\ValueObject\InvoiceNumber;
use App\Domain\Invoice\ValueObject\InvoiceStatus;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineInvoiceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: DoctrineInvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
class InvoiceEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $number;

    #[ORM\Column(type: Types::STRING)]
    private string $customerId;

    #[ORM\Column(type: Types::STRING)]
    private string $status;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $issueDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $dueDate;

    #[ORM\Column(type: Types::JSON)]
    private array $items = [];

    #[ORM\Column(type: Types::FLOAT)]
    private float $totalAmount;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public static function fromDomain(Invoice $invoice): self
    {
        $entity = new self();
        $entity->id = \Ramsey\Uuid\Uuid::fromString($invoice->id()->value());
        $entity->number = $invoice->number()->value();
        $entity->customerId = $invoice->customerId();
        $entity->status = $invoice->status()->value();
        $entity->issueDate = $invoice->issueDate();
        $entity->dueDate = $invoice->dueDate();
        $entity->items = array_map(
            fn(InvoiceItem $item) => [
                'productId' => $item->productId(),
                'description' => $item->description(),
                'quantity' => $item->quantity(),
                'unitPrice' => $item->unitPrice()->amount(),
                'currency' => $item->unitPrice()->currency(),
            ],
            $invoice->items()
        );
        $entity->totalAmount = $invoice->totalAmount()->amount();
        $entity->currency = $invoice->totalAmount()->currency();
        $entity->createdAt = $invoice->createdAt();
        $entity->updatedAt = $invoice->updatedAt();

        return $entity;
    }

    public function toDomain(): Invoice
    {
        $invoiceId = InvoiceId::fromString($this->id->toString());
        $invoiceNumber = InvoiceNumber::fromString($this->number);
        $status = match ($this->status) {
            'draft' => InvoiceStatus::draft(),
            'issued' => InvoiceStatus::issued(),
            'paid' => InvoiceStatus::paid(),
            'cancelled' => InvoiceStatus::cancelled(),
            default => InvoiceStatus::draft(),
        };

        $invoice = Invoice::create(
            $invoiceId,
            $invoiceNumber,
            $this->customerId,
            $this->issueDate,
            $this->dueDate
        );

        // Use reflection to set private properties (for reconstruction)
        $reflection = new \ReflectionClass($invoice);
        
        // Set issueDate and dueDate
        $issueDateProperty = $reflection->getProperty('issueDate');
        $issueDateProperty->setAccessible(true);
        $issueDateProperty->setValue($invoice, $this->issueDate);
        
        $dueDateProperty = $reflection->getProperty('dueDate');
        $dueDateProperty->setAccessible(true);
        $dueDateProperty->setValue($invoice, $this->dueDate);
        
        // Set status
        $statusProperty = $reflection->getProperty('status');
        $statusProperty->setAccessible(true);
        $statusProperty->setValue($invoice, $status);

        // Set items
        $itemsProperty = $reflection->getProperty('items');
        $itemsProperty->setAccessible(true);
        $items = array_map(
            fn(array $itemData) => new InvoiceItem(
                $itemData['productId'],
                $itemData['description'],
                $itemData['quantity'],
                \App\Domain\Shared\ValueObject\Money::fromFloat(
                    $itemData['unitPrice'],
                    $itemData['currency'] ?? 'USD'
                )
            ),
            $this->items
        );
        $itemsProperty->setValue($invoice, $items);

        // Recalculate total after setting items
        $totalAmountProperty = $reflection->getProperty('totalAmount');
        $totalAmountProperty->setAccessible(true);
        $total = \App\Domain\Shared\ValueObject\Money::zero($this->currency);
        foreach ($items as $item) {
            $total = $total->add($item->totalPrice());
        }
        $totalAmountProperty->setValue($invoice, $total);

        // Set timestamps
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($invoice, $this->createdAt);

        $updatedAtProperty = $reflection->getProperty('updatedAt');
        $updatedAtProperty->setAccessible(true);
        $updatedAtProperty->setValue($invoice, $this->updatedAt);

        return $invoice;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function updateFromDomain(Invoice $invoice): void
    {
        $this->status = $invoice->status()->value();
        $this->items = array_map(
            fn(InvoiceItem $item) => [
                'productId' => $item->productId(),
                'description' => $item->description(),
                'quantity' => $item->quantity(),
                'unitPrice' => $item->unitPrice()->amount(),
                'currency' => $item->unitPrice()->currency(),
            ],
            $invoice->items()
        );
        $this->totalAmount = $invoice->totalAmount()->amount();
        $this->currency = $invoice->totalAmount()->currency();
        $this->updatedAt = new DateTimeImmutable();
    }
}
