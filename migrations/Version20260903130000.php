<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hace opcionales hora_inicio y hora_fin en franja_horaria_personalizada';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE franja_horaria_personalizada CHANGE hora_inicio hora_inicio TIME DEFAULT NULL');
        $this->addSql('ALTER TABLE franja_horaria_personalizada CHANGE hora_fin hora_fin TIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE franja_horaria_personalizada CHANGE hora_inicio hora_inicio TIME NOT NULL');
        $this->addSql('ALTER TABLE franja_horaria_personalizada CHANGE hora_fin hora_fin TIME NOT NULL');
    }
}
