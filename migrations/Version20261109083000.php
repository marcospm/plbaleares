<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109083000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite guardar un examen semanal como borrador, con la última versión de las preguntas del prompt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE examen_semanal ADD borrador TINYINT(1) DEFAULT 0 NOT NULL, ADD bloque_borrador LONGTEXT DEFAULT NULL, ADD borrador_meta JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE examen_semanal DROP borrador, DROP bloque_borrador, DROP borrador_meta');
    }
}
