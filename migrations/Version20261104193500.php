<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104193500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade el PDF opcional de bases a las convocatorias';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE convocatoria ADD ruta_bases_pdf VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE convocatoria DROP ruta_bases_pdf');
    }
}
