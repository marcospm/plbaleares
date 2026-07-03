<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite enlaces externos en recursos específicos y hace opcional la ruta de archivo';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recurso_especifico ADD enlace LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE recurso_especifico CHANGE ruta_archivo ruta_archivo VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recurso_especifico DROP enlace');
        $this->addSql('ALTER TABLE recurso_especifico CHANGE ruta_archivo ruta_archivo VARCHAR(500) NOT NULL');
    }
}
