<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251222121252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE recurso_especifico (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, ruta_archivo VARCHAR(500) NOT NULL, nombre_archivo_original VARCHAR(255) DEFAULT NULL, fecha_creacion DATETIME NOT NULL, profesor_id INT NOT NULL, INDEX IDX_EFEBDD8CE52BD977 (profesor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE recurso_especifico_alumno (recurso_especifico_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_DD081A798D20A799 (recurso_especifico_id), INDEX IDX_DD081A79A76ED395 (user_id), PRIMARY KEY (recurso_especifico_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE recurso_especifico ADD CONSTRAINT FK_EFEBDD8CE52BD977 FOREIGN KEY (profesor_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE recurso_especifico_alumno ADD CONSTRAINT FK_DD081A798D20A799 FOREIGN KEY (recurso_especifico_id) REFERENCES recurso_especifico (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recurso_especifico_alumno ADD CONSTRAINT FK_DD081A79A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recurso_especifico DROP FOREIGN KEY FK_EFEBDD8CE52BD977');
        $this->addSql('ALTER TABLE recurso_especifico_alumno DROP FOREIGN KEY FK_DD081A798D20A799');
        $this->addSql('ALTER TABLE recurso_especifico_alumno DROP FOREIGN KEY FK_DD081A79A76ED395');
        $this->addSql('DROP TABLE recurso_especifico');
        $this->addSql('DROP TABLE recurso_especifico_alumno');
    }
}
