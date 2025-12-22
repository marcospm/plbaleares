<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251222070517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE examen_pdf (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) NOT NULL, descripcion LONGTEXT DEFAULT NULL, ruta_archivo VARCHAR(500) NOT NULL, fecha_subida DATETIME NOT NULL, tema_id INT DEFAULT NULL, INDEX IDX_75FB0120A64A8A17 (tema_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE examen_pdf ADD CONSTRAINT FK_75FB0120A64A8A17 FOREIGN KEY (tema_id) REFERENCES tema (id)');
        $this->addSql('ALTER TABLE planificacion_semanal ADD fecha_fin DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE examen_pdf DROP FOREIGN KEY FK_75FB0120A64A8A17');
        $this->addSql('DROP TABLE examen_pdf');
        $this->addSql('ALTER TABLE planificacion_semanal DROP fecha_fin');
    }
}
