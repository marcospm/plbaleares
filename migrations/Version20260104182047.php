<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260104182047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade relación opcional con Grupo en ExamenSemanal';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE examen_semanal ADD grupo_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE examen_semanal ADD CONSTRAINT FK_examen_semanal_grupo FOREIGN KEY (grupo_id) REFERENCES grupo (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_examen_semanal_grupo ON examen_semanal (grupo_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE examen_semanal DROP FOREIGN KEY FK_examen_semanal_grupo');
        $this->addSql('DROP INDEX IDX_examen_semanal_grupo ON examen_semanal');
        $this->addSql('ALTER TABLE examen_semanal DROP grupo_id');
    }
}
