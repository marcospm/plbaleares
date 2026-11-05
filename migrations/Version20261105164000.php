<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105164000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrupa los archivos de un mismo recurso específico';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recurso_especifico ADD lote VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recurso_especifico DROP lote');
    }
}
