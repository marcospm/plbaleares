<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109115000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade la categoría de oposición al usuario. Los usuarios existentes quedan en Policía.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD categoria VARCHAR(20) DEFAULT 'policia' NOT NULL");
        $this->addSql("UPDATE user SET categoria = 'policia'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP categoria');
    }
}
