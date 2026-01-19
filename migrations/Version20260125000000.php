<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add Plantilla and PlantillaMunicipal entities for exam templates
 */
final class Version20260125000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Plantilla and PlantillaMunicipal entities for organizing questions into templates';
    }

    public function up(Schema $schema): void
    {
        // Create plantilla table
        $this->addSql('CREATE TABLE plantilla (id INT AUTO_INCREMENT NOT NULL, tema_id INT NOT NULL, nombre VARCHAR(255) NOT NULL, dificultad VARCHAR(20) NOT NULL, INDEX IDX_PLANTILLA_TEMA (tema_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE plantilla ADD CONSTRAINT FK_PLANTILLA_TEMA FOREIGN KEY (tema_id) REFERENCES tema (id) ON DELETE CASCADE');

        // Create plantilla_municipal table
        $this->addSql('CREATE TABLE plantilla_municipal (id INT AUTO_INCREMENT NOT NULL, tema_municipal_id INT NOT NULL, nombre VARCHAR(255) NOT NULL, dificultad VARCHAR(20) NOT NULL, INDEX IDX_PLANTILLA_MUNICIPAL_TEMA (tema_municipal_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE plantilla_municipal ADD CONSTRAINT FK_PLANTILLA_MUNICIPAL_TEMA FOREIGN KEY (tema_municipal_id) REFERENCES tema_municipal (id) ON DELETE CASCADE');

        // Add plantilla_id to pregunta table (nullable first, then make NOT NULL after data migration)
        $this->addSql('ALTER TABLE pregunta ADD plantilla_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_PREGUNTA_PLANTILLA ON pregunta (plantilla_id)');
        $this->addSql('ALTER TABLE pregunta ADD CONSTRAINT FK_PREGUNTA_PLANTILLA FOREIGN KEY (plantilla_id) REFERENCES plantilla (id) ON DELETE RESTRICT');
        
        // NOTA: Después de ejecutar esta migración, deberás:
        // 1. Crear plantillas para todas las combinaciones de tema/dificultad existentes
        // 2. Asignar las preguntas existentes a plantillas
        // 3. Ejecutar: ALTER TABLE pregunta MODIFY plantilla_id INT NOT NULL;
        // 4. Ejecutar: ALTER TABLE pregunta_municipal MODIFY plantilla_id INT NOT NULL;

        // Add plantilla_id to pregunta_municipal table (nullable first)
        $this->addSql('ALTER TABLE pregunta_municipal ADD plantilla_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_PREGUNTA_MUNICIPAL_PLANTILLA ON pregunta_municipal (plantilla_id)');
        $this->addSql('ALTER TABLE pregunta_municipal ADD CONSTRAINT FK_PREGUNTA_MUNICIPAL_PLANTILLA FOREIGN KEY (plantilla_id) REFERENCES plantilla_municipal (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // Remove foreign keys and columns
        $this->addSql('ALTER TABLE pregunta DROP FOREIGN KEY FK_PREGUNTA_PLANTILLA');
        $this->addSql('DROP INDEX IDX_PREGUNTA_PLANTILLA ON pregunta');
        $this->addSql('ALTER TABLE pregunta DROP plantilla_id');

        $this->addSql('ALTER TABLE pregunta_municipal DROP FOREIGN KEY FK_PREGUNTA_MUNICIPAL_PLANTILLA');
        $this->addSql('DROP INDEX IDX_PREGUNTA_MUNICIPAL_PLANTILLA ON pregunta_municipal');
        $this->addSql('ALTER TABLE pregunta_municipal DROP plantilla_id');

        // Drop tables
        $this->addSql('DROP TABLE plantilla_municipal');
        $this->addSql('DROP TABLE plantilla');
    }
}
