<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250125000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create invoices table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        
        $this->addSql('CREATE TABLE invoices (
            id UUID PRIMARY KEY,
            number VARCHAR(255) UNIQUE NOT NULL,
            customer_id VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL,
            issue_date DATE NOT NULL,
            due_date DATE NOT NULL,
            items JSON NOT NULL,
            total_amount DOUBLE PRECISION NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');
        
        $this->addSql('CREATE INDEX idx_invoices_customer_id ON invoices(customer_id)');
        $this->addSql('CREATE INDEX idx_invoices_status ON invoices(status)');
        $this->addSql('CREATE INDEX idx_invoices_number ON invoices(number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE invoices');
    }
}
