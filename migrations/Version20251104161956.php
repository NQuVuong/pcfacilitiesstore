<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251104161956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` ADD refundable_remaining INT DEFAULT 0 NOT NULL, ADD refunded_total INT DEFAULT 0 NOT NULL, ADD last_refund_request_id VARCHAR(64) DEFAULT NULL, ADD last_refund_order_id VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP refundable_remaining, DROP refunded_total, DROP last_refund_request_id, DROP last_refund_order_id');
    }
}
