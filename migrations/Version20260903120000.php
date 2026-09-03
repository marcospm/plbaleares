<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade modo horario/rango a planificacion_personalizada';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE planificacion_personalizada ADD modo VARCHAR(20) NOT NULL DEFAULT 'horario'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planificacion_personalizada DROP modo');
    }
}
