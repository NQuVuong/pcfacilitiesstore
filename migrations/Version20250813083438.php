<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250813083438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE price DROP FOREIGN KEY FK_CAC822D94584665A');
        $this->addSql('ALTER TABLE price ADD export_price DOUBLE PRECISION DEFAULT NULL, ADD created_at DATETIME NOT NULL, DROP price_type, CHANGE product_id product_id INT NOT NULL, CHANGE price import_price DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE price ADD CONSTRAINT FK_CAC822D94584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product DROP price_import, DROP price_export, CHANGE created created DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE price DROP FOREIGN KEY FK_CAC822D94584665A');
        $this->addSql('ALTER TABLE price ADD price_type VARCHAR(255) NOT NULL, DROP export_price, DROP created_at, CHANGE product_id product_id INT DEFAULT NULL, CHANGE import_price price DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE price ADD CONSTRAINT FK_CAC822D94584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE product ADD price_import DOUBLE PRECISION NOT NULL, ADD price_export DOUBLE PRECISION NOT NULL, CHANGE created created DATE NOT NULL');
    }
}
