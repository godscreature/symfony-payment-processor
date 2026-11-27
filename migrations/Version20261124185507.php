<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124185507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment details to orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD payment_gateway VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD payment_transaction_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP payment_gateway');
        $this->addSql('ALTER TABLE orders DROP payment_transaction_id');
        $this->addSql('ALTER TABLE orders DROP paid_at');
    }
}
