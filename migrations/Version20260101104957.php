<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101104957 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agregar campo rutaArchivoRespuestas a la tabla examen_pdf';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE examen_pdf ADD ruta_archivo_respuestas VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE examen_pdf DROP COLUMN ruta_archivo_respuestas');
    }
}
