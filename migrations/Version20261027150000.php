<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Elimina el DNI de los usuarios';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP dni');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD dni VARCHAR(20) DEFAULT NULL');
    }
}
