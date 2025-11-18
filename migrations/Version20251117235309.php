<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117235309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE visit DROP FOREIGN KEY FK_437EE9394584665A');
        $this->addSql('DROP INDEX IDX_437EE9394584665A ON visit');
        $this->addSql('ALTER TABLE visit ADD ip VARCHAR(45) DEFAULT NULL, DROP product_id, CHANGE route_name route_name VARCHAR(100) NOT NULL, CHANGE browser browser VARCHAR(50) NOT NULL, CHANGE created_at visited_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE visit ADD product_id INT DEFAULT NULL, DROP ip, CHANGE route_name route_name VARCHAR(100) DEFAULT NULL, CHANGE browser browser VARCHAR(100) DEFAULT NULL, CHANGE visited_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE visit ADD CONSTRAINT FK_437EE9394584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('CREATE INDEX IDX_437EE9394584665A ON visit (product_id)');
    }
}
