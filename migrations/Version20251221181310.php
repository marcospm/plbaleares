<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221181310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE franja_horaria_personalizada DROP FOREIGN KEY `FK_DB0B15453B8909C5`');
        $this->addSql('ALTER TABLE franja_horaria_personalizada ADD CONSTRAINT FK_DB0B15453B8909C5 FOREIGN KEY (franja_base_id) REFERENCES franja_horaria (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE franja_horaria_personalizada DROP FOREIGN KEY FK_DB0B15453B8909C5');
        $this->addSql('ALTER TABLE franja_horaria_personalizada ADD CONSTRAINT `FK_DB0B15453B8909C5` FOREIGN KEY (franja_base_id) REFERENCES franja_horaria (id)');
    }
}
