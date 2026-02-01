<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add ExamenConvocatoria entity for exams from other convocatorias
 */
final class Version20260201000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ExamenConvocatoria entity for storing exams from other convocatorias with questions and answers PDFs';
    }

    public function up(Schema $schema): void
    {
        // Create examen_convocatoria table
        $this->addSql('CREATE TABLE examen_convocatoria (
            id INT AUTO_INCREMENT NOT NULL, 
            nombre VARCHAR(255) NOT NULL, 
            descripcion LONGTEXT DEFAULT NULL, 
            ruta_archivo VARCHAR(500) NOT NULL, 
            ruta_archivo_respuestas VARCHAR(500) DEFAULT NULL, 
            fecha_subida DATETIME NOT NULL, 
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // Drop table
        $this->addSql('DROP TABLE examen_convocatoria');
    }
}
