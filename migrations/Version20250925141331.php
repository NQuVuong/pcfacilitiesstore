<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250925141331 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` ADD customer_name VARCHAR(255) DEFAULT NULL, ADD customer_email VARCHAR(255) DEFAULT NULL, ADD customer_phone VARCHAR(30) DEFAULT NULL, ADD shipping_address LONGTEXT DEFAULT NULL, ADD note LONGTEXT DEFAULT NULL, ADD payment_method VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` DROP customer_name, DROP customer_email, DROP customer_phone, DROP shipping_address, DROP note, DROP payment_method');
    }
}
