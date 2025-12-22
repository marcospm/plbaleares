<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251222122101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notificacion (id INT AUTO_INCREMENT NOT NULL, tipo VARCHAR(50) NOT NULL, titulo VARCHAR(255) NOT NULL, mensaje LONGTEXT DEFAULT NULL, leida TINYINT DEFAULT 0 NOT NULL, fecha_creacion DATETIME NOT NULL, profesor_id INT NOT NULL, alumno_id INT NOT NULL, examen_id INT DEFAULT NULL, tarea_asignada_id INT DEFAULT NULL, INDEX IDX_729A19ECE52BD977 (profesor_id), INDEX IDX_729A19ECFC28E5EE (alumno_id), INDEX IDX_729A19EC5C8659A (examen_id), INDEX IDX_729A19ECBA971778 (tarea_asignada_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notificacion ADD CONSTRAINT FK_729A19ECE52BD977 FOREIGN KEY (profesor_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE notificacion ADD CONSTRAINT FK_729A19ECFC28E5EE FOREIGN KEY (alumno_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE notificacion ADD CONSTRAINT FK_729A19EC5C8659A FOREIGN KEY (examen_id) REFERENCES examen (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notificacion ADD CONSTRAINT FK_729A19ECBA971778 FOREIGN KEY (tarea_asignada_id) REFERENCES tarea_asignada (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notificacion DROP FOREIGN KEY FK_729A19ECE52BD977');
        $this->addSql('ALTER TABLE notificacion DROP FOREIGN KEY FK_729A19ECFC28E5EE');
        $this->addSql('ALTER TABLE notificacion DROP FOREIGN KEY FK_729A19EC5C8659A');
        $this->addSql('ALTER TABLE notificacion DROP FOREIGN KEY FK_729A19ECBA971778');
        $this->addSql('DROP TABLE notificacion');
    }
}
