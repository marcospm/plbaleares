<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251223153732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notificacion ADD planificacion_semanal_id INT DEFAULT NULL, ADD tarea_id INT DEFAULT NULL, CHANGE profesor_id profesor_id INT DEFAULT NULL, CHANGE alumno_id alumno_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notificacion ADD CONSTRAINT FK_729A19ECE94111C FOREIGN KEY (planificacion_semanal_id) REFERENCES planificacion_semanal (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notificacion ADD CONSTRAINT FK_729A19EC6D5BDFE1 FOREIGN KEY (tarea_id) REFERENCES tarea (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_729A19ECE94111C ON notificacion (planificacion_semanal_id)');
        $this->addSql('CREATE INDEX IDX_729A19EC6D5BDFE1 ON notificacion (tarea_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notificacion DROP FOREIGN KEY FK_729A19ECE94111C');
        $this->addSql('ALTER TABLE notificacion DROP FOREIGN KEY FK_729A19EC6D5BDFE1');
        $this->addSql('DROP INDEX IDX_729A19ECE94111C ON notificacion');
        $this->addSql('DROP INDEX IDX_729A19EC6D5BDFE1 ON notificacion');
        $this->addSql('ALTER TABLE notificacion DROP planificacion_semanal_id, DROP tarea_id, CHANGE profesor_id profesor_id INT NOT NULL, CHANGE alumno_id alumno_id INT NOT NULL');
    }
}
