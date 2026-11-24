<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123174951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create orders and order_items tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE order_items (id UUID NOT NULL, product_id UUID NOT NULL, quantity INT NOT NULL, unit_price INT NOT NULL, order_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_62809DB08D9F6D38 ON order_items (order_id)');
        $this->addSql('CREATE TABLE orders (id UUID NOT NULL, customer_id UUID NOT NULL, status VARCHAR(16) NOT NULL, currency VARCHAR(3) NOT NULL, total_amount BIGINT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, version INT DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_orders_customer_id ON orders (customer_id)');
        $this->addSql('CREATE INDEX idx_orders_status ON orders (status)');
        $this->addSql('CREATE INDEX idx_orders_created_at ON orders (created_at)');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB08D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_items DROP CONSTRAINT FK_62809DB08D9F6D38');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
    }
}
