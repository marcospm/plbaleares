<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221163240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE articulo ADD activo TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE ley ADD activo TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE pregunta ADD activo TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE tema ADD activo TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE articulo DROP activo');
        $this->addSql('ALTER TABLE ley DROP activo');
        $this->addSql('ALTER TABLE pregunta DROP activo');
        $this->addSql('ALTER TABLE tema DROP activo');
    }
}
