<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Añadir campo numero_plazas a la tabla municipio
 */
final class Version20260120000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añadir campo numero_plazas (opcional) a la tabla municipio';
    }

    public function up(Schema $schema): void
    {
        // Añadir columna numero_plazas a la tabla municipio
        $this->addSql('ALTER TABLE municipio ADD numero_plazas INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Eliminar columna numero_plazas de la tabla municipio
        $this->addSql('ALTER TABLE municipio DROP numero_plazas');
    }
}
