<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Elimina IBAN y banco de los usuarios';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP iban, DROP banco');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD iban VARCHAR(34) DEFAULT NULL, ADD banco VARCHAR(100) DEFAULT NULL');
    }
}
