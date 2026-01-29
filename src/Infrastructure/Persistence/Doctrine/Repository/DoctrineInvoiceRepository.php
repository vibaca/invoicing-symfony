<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\Repository\InvoiceRepository;
use App\Domain\Invoice\ValueObject\InvoiceId;
use App\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineInvoiceRepository extends ServiceEntityRepository implements InvoiceRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceEntity::class);
    }

    public function save(Invoice $invoice): void
    {
        /** @var InvoiceEntity|null $entity */
        $entity = $this->getEntityManager()
            ->getRepository(InvoiceEntity::class)
            ->findOneBy(['id' => \Ramsey\Uuid\Uuid::fromString($invoice->id()->value())]);

        if ($entity === null) {
            $entity = InvoiceEntity::fromDomain($invoice);
            $this->getEntityManager()->persist($entity);
        } else {
            $entity->updateFromDomain($invoice);
        }

        $this->getEntityManager()->flush();
    }

    public function findById(InvoiceId $id): ?Invoice
    {
        /** @var InvoiceEntity|null $entity */
        $entity = $this->find(\Ramsey\Uuid\Uuid::fromString($id->value()));

        if ($entity === null) {
            return null;
        }

        return $entity->toDomain();
    }

    public function findByNumber(string $number): ?Invoice
    {
        /** @var InvoiceEntity|null $entity */
        $entity = $this->findOneBy(['number' => $number]);

        if ($entity === null) {
            return null;
        }

        return $entity->toDomain();
    }

    public function findAll(): array
    {
        /** @var array<InvoiceEntity> $entities */
        $entities = parent::findAll();

        return array_map(
            fn(InvoiceEntity $entity) => $entity->toDomain(),
            $entities
        );
    }
}
