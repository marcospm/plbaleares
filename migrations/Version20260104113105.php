<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260104113105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade relación opcional con Grupo en RecursoEspecifico';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recurso_especifico ADD grupo_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE recurso_especifico ADD CONSTRAINT FK_RECURSO_ESPECIFICO_GRUPO FOREIGN KEY (grupo_id) REFERENCES grupo (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_RECURSO_ESPECIFICO_GRUPO ON recurso_especifico (grupo_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recurso_especifico DROP FOREIGN KEY FK_RECURSO_ESPECIFICO_GRUPO');
        $this->addSql('DROP INDEX IDX_RECURSO_ESPECIFICO_GRUPO ON recurso_especifico');
        $this->addSql('ALTER TABLE recurso_especifico DROP grupo_id');
    }
}

