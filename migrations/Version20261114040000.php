<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrupa varios archivos de psicotécnicos subidos a la vez.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE archivo_psicotecnico ADD lote VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_archivo_psicotecnico_lote ON archivo_psicotecnico (lote)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_archivo_psicotecnico_lote ON archivo_psicotecnico');
        $this->addSql('ALTER TABLE archivo_psicotecnico DROP lote');
    }
}
