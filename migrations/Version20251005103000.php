<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251005103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category_request (id INT AUTO_INCREMENT NOT NULL, requested_by_id INT NOT NULL, decided_by_id INT DEFAULT NULL, name VARCHAR(120) NOT NULL, reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(20) NOT NULL, decided_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FC4FCFE14DA1E751 (requested_by_id), INDEX IDX_FC4FCFE1E26B496B (decided_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE supplier (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, email VARCHAR(150) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE category_request ADD CONSTRAINT FK_FC4FCFE14DA1E751 FOREIGN KEY (requested_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE category_request ADD CONSTRAINT FK_FC4FCFE1E26B496B FOREIGN KEY (decided_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category_request DROP FOREIGN KEY FK_FC4FCFE14DA1E751');
        $this->addSql('ALTER TABLE category_request DROP FOREIGN KEY FK_FC4FCFE1E26B496B');
        $this->addSql('DROP TABLE category_request');
        $this->addSql('DROP TABLE supplier');
    }
}
