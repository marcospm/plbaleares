<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251225115701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mensaje_pregunta (id INT AUTO_INCREMENT NOT NULL, mensaje LONGTEXT NOT NULL, fecha_creacion DATETIME NOT NULL, es_respuesta TINYINT DEFAULT 0 NOT NULL, pregunta_id INT NOT NULL, autor_id INT NOT NULL, mensaje_padre_id INT DEFAULT NULL, INDEX IDX_BD1A24D631A5801E (pregunta_id), INDEX IDX_BD1A24D614D45BBE (autor_id), INDEX IDX_BD1A24D672C62979 (mensaje_padre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mensaje_pregunta ADD CONSTRAINT FK_BD1A24D631A5801E FOREIGN KEY (pregunta_id) REFERENCES pregunta (id)');
        $this->addSql('ALTER TABLE mensaje_pregunta ADD CONSTRAINT FK_BD1A24D614D45BBE FOREIGN KEY (autor_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE mensaje_pregunta ADD CONSTRAINT FK_BD1A24D672C62979 FOREIGN KEY (mensaje_padre_id) REFERENCES mensaje_pregunta (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notificacion ADD pregunta_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notificacion ADD CONSTRAINT FK_729A19EC31A5801E FOREIGN KEY (pregunta_id) REFERENCES pregunta (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_729A19EC31A5801E ON notificacion (pregunta_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mensaje_pregunta DROP FOREIGN KEY FK_BD1A24D631A5801E');
        $this->addSql('ALTER TABLE mensaje_pregunta DROP FOREIGN KEY FK_BD1A24D614D45BBE');
        $this->addSql('ALTER TABLE mensaje_pregunta DROP FOREIGN KEY FK_BD1A24D672C62979');
        $this->addSql('DROP TABLE mensaje_pregunta');
        $this->addSql('ALTER TABLE notificacion DROP FOREIGN KEY FK_729A19EC31A5801E');
        $this->addSql('DROP INDEX IDX_729A19EC31A5801E ON notificacion');
        $this->addSql('ALTER TABLE notificacion DROP pregunta_id');
    }
}
