<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Elimina el campo modo de planificacion_personalizada';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planificacion_personalizada DROP COLUMN modo');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE planificacion_personalizada ADD modo VARCHAR(20) NOT NULL DEFAULT 'horario'");
    }
}
